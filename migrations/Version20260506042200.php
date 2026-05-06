<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds CHECK constraints and PostgreSQL triggers from the ERD spec.
 *
 * - Depth bounds on theme and region trees
 * - Enum value checks for status and kind
 * - Publish gate: live status requires data_count >= 5, intro_text, verified author
 * - Auto-refresh data_count from verified data_points
 * - Parent silo integrity: child cannot go live while parent is non-live
 * - Auto redirect creation when a node transitions to dead
 */
final class Version20260506042200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ERD-mandated CHECK constraints and triggers (publish gate, data_count refresh, parent live check, dead cascade redirect)';
    }

    public function up(Schema $schema): void
    {
        $this->addCheckConstraints();
        $this->addDataCountRefreshTrigger();
        $this->addPublishGateTrigger();
        $this->addParentLiveCheckTrigger();
        $this->addDeadCascadeRedirectTrigger();
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TRIGGER IF EXISTS trg_dead_cascade_redirect ON content_node');
        $this->addSql('DROP FUNCTION IF EXISTS fn_dead_cascade_redirect()');

        $this->addSql('DROP TRIGGER IF EXISTS trg_parent_live_check ON content_node');
        $this->addSql('DROP FUNCTION IF EXISTS fn_parent_live_check()');

        $this->addSql('DROP TRIGGER IF EXISTS trg_publish_gate ON content_node');
        $this->addSql('DROP FUNCTION IF EXISTS fn_publish_gate()');

        $this->addSql('DROP TRIGGER IF EXISTS trg_refresh_data_count ON data_point');
        $this->addSql('DROP FUNCTION IF EXISTS fn_refresh_data_count()');

        $this->addSql('ALTER TABLE data_point DROP CONSTRAINT IF EXISTS chk_data_point_kind');
        $this->addSql('ALTER TABLE content_node DROP CONSTRAINT IF EXISTS chk_content_node_status');
        $this->addSql('ALTER TABLE region DROP CONSTRAINT IF EXISTS chk_region_depth');
        $this->addSql('ALTER TABLE theme DROP CONSTRAINT IF EXISTS chk_theme_depth');
    }

    private function addCheckConstraints(): void
    {
        $this->addSql('ALTER TABLE theme ADD CONSTRAINT chk_theme_depth CHECK (depth >= 0 AND depth <= 2)');
        $this->addSql('ALTER TABLE region ADD CONSTRAINT chk_region_depth CHECK (depth >= 0 AND depth <= 3)');
        $this->addSql("ALTER TABLE content_node ADD CONSTRAINT chk_content_node_status CHECK (status IN ('draft','live','noindex','dead'))");
        $this->addSql("ALTER TABLE data_point ADD CONSTRAINT chk_data_point_kind CHECK (kind IN ('quantitative','qualitative','comparison','case','faq'))");
    }

    private function addDataCountRefreshTrigger(): void
    {
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_refresh_data_count() RETURNS TRIGGER AS $$
            BEGIN
                IF TG_OP IN ('INSERT', 'UPDATE') AND NEW.node_id IS NOT NULL THEN
                    UPDATE content_node
                    SET data_count = (
                        SELECT COUNT(*) FROM data_point
                        WHERE node_id = NEW.node_id AND verified = true
                    )
                    WHERE id = NEW.node_id;
                END IF;

                IF TG_OP IN ('UPDATE', 'DELETE') AND OLD.node_id IS NOT NULL
                   AND (TG_OP = 'DELETE' OR OLD.node_id <> NEW.node_id) THEN
                    UPDATE content_node
                    SET data_count = (
                        SELECT COUNT(*) FROM data_point
                        WHERE node_id = OLD.node_id AND verified = true
                    )
                    WHERE id = OLD.node_id;
                END IF;

                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TRIGGER trg_refresh_data_count
            AFTER INSERT OR UPDATE OR DELETE ON data_point
            FOR EACH ROW EXECUTE FUNCTION fn_refresh_data_count()
        SQL);
    }

    private function addPublishGateTrigger(): void
    {
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_publish_gate() RETURNS TRIGGER AS $$
            DECLARE
                author_verified TIMESTAMP;
            BEGIN
                IF NEW.status <> 'live' THEN
                    RETURN NEW;
                END IF;

                IF NEW.data_count < 5 THEN
                    RAISE EXCEPTION 'Publish gate: content_node % requires data_count >= 5 (have %)', NEW.id, NEW.data_count;
                END IF;

                IF NEW.intro_text IS NULL OR LENGTH(TRIM(NEW.intro_text)) = 0 THEN
                    RAISE EXCEPTION 'Publish gate: content_node % requires non-empty intro_text', NEW.id;
                END IF;

                IF NEW.author_id IS NULL THEN
                    RAISE EXCEPTION 'Publish gate: content_node % requires author_id', NEW.id;
                END IF;

                SELECT verified_at INTO author_verified FROM author WHERE id = NEW.author_id;
                IF author_verified IS NULL THEN
                    RAISE EXCEPTION 'Publish gate: content_node % requires verified author (id=%)', NEW.id, NEW.author_id;
                END IF;

                IF NEW.first_published_at IS NULL THEN
                    NEW.first_published_at := NOW();
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TRIGGER trg_publish_gate
            BEFORE INSERT OR UPDATE ON content_node
            FOR EACH ROW EXECUTE FUNCTION fn_publish_gate()
        SQL);
    }

    private function addParentLiveCheckTrigger(): void
    {
        // Parent of a content_node is the node sharing the same theme, sitting one
        // region-tree step closer to the root. The theme hub (region_id IS NULL)
        // has no parent in this dimension and is exempt.
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_parent_live_check() RETURNS TRIGGER AS $$
            DECLARE
                parent_region_id INT;
                parent_status VARCHAR(20);
            BEGIN
                IF NEW.status <> 'live' THEN
                    RETURN NEW;
                END IF;

                IF NEW.region_id IS NULL THEN
                    RETURN NEW;
                END IF;

                SELECT parent_id INTO parent_region_id FROM region WHERE id = NEW.region_id;

                IF parent_region_id IS NULL THEN
                    SELECT status INTO parent_status
                    FROM content_node
                    WHERE theme_id = NEW.theme_id AND region_id IS NULL;
                ELSE
                    SELECT status INTO parent_status
                    FROM content_node
                    WHERE theme_id = NEW.theme_id AND region_id = parent_region_id;
                END IF;

                IF parent_status IS NULL THEN
                    RAISE EXCEPTION 'Parent live check: content_node % has no parent node (theme=%, region_parent=%)', NEW.id, NEW.theme_id, parent_region_id;
                END IF;

                IF parent_status <> 'live' THEN
                    RAISE EXCEPTION 'Parent live check: content_node % cannot go live while parent is % (theme=%, region_parent=%)', NEW.id, parent_status, NEW.theme_id, parent_region_id;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TRIGGER trg_parent_live_check
            BEFORE INSERT OR UPDATE ON content_node
            FOR EACH ROW EXECUTE FUNCTION fn_parent_live_check()
        SQL);
    }

    private function addDeadCascadeRedirectTrigger(): void
    {
        // When a node transitions to dead, register a 301 redirect to the closest
        // live ancestor in the same theme. If none exists, no redirect is created.
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_dead_cascade_redirect() RETURNS TRIGGER AS $$
            DECLARE
                ancestor_region_id INT;
                ancestor_node_id INT;
            BEGIN
                IF NEW.status <> 'dead' OR (TG_OP = 'UPDATE' AND OLD.status = 'dead') THEN
                    RETURN NEW;
                END IF;

                ancestor_region_id := NEW.region_id;

                LOOP
                    IF ancestor_region_id IS NULL THEN
                        SELECT id INTO ancestor_node_id
                        FROM content_node
                        WHERE theme_id = NEW.theme_id
                          AND region_id IS NULL
                          AND status = 'live'
                          AND id <> NEW.id;
                        EXIT;
                    END IF;

                    SELECT parent_id INTO ancestor_region_id FROM region WHERE id = ancestor_region_id;

                    IF ancestor_region_id IS NULL THEN
                        CONTINUE;
                    END IF;

                    SELECT id INTO ancestor_node_id
                    FROM content_node
                    WHERE theme_id = NEW.theme_id
                      AND region_id = ancestor_region_id
                      AND status = 'live'
                      AND id <> NEW.id;

                    EXIT WHEN ancestor_node_id IS NOT NULL;
                END LOOP;

                IF ancestor_node_id IS NOT NULL THEN
                    INSERT INTO redirect (from_node_id, to_node_id, http_status, created_at)
                    VALUES (NEW.id, ancestor_node_id, 301, NOW())
                    ON CONFLICT DO NOTHING;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TRIGGER trg_dead_cascade_redirect
            AFTER UPDATE OF status ON content_node
            FOR EACH ROW EXECUTE FUNCTION fn_dead_cascade_redirect()
        SQL);
    }
}
