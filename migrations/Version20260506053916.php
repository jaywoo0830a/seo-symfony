<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Make the publish gate fail-soft for already-live nodes.
 *
 * Before:
 *   Any UPDATE on a live node (including the cascade UPDATE from
 *   fn_refresh_data_count when a verified DataPoint is removed)
 *   would re-run the gate and reject if data_count < 5.
 *   Result: the operator cannot un-verify or delete a DataPoint
 *   on a live node without first manually demoting the node.
 *
 * After:
 *   - Transitions INTO 'live' (INSERT or status change) still enforce
 *     the full gate (data_count >= 5, intro_text, verified author).
 *   - For an already-live node whose data_count drops below 5,
 *     the gate auto-demotes status to 'noindex' (matches the
 *     "live → noindex (data dropped)" transition in the state machine).
 *   - Other in-place UPDATEs to live nodes pass through unchanged.
 */
final class Version20260506053916 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Publish gate: auto-demote live nodes to noindex when data_count drops below 5 (instead of raising)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_publish_gate() RETURNS TRIGGER AS $$
            DECLARE
                author_verified TIMESTAMP;
            BEGIN
                IF NEW.status <> 'live' THEN
                    RETURN NEW;
                END IF;

                -- Already-live node: tolerate fluctuation, auto-demote when data drops.
                IF TG_OP = 'UPDATE' AND OLD.status = 'live' THEN
                    IF NEW.data_count < 5 THEN
                        NEW.status := 'noindex';
                    END IF;
                    RETURN NEW;
                END IF;

                -- INSERT into live, or transition non-live → live: enforce full gate.
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
    }

    public function down(Schema $schema): void
    {
        // Restore the strict version from Version20260506043833.
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
    }
}
