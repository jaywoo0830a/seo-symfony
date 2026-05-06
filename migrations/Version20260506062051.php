<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fix fn_parent_live_check to map "country root parent" to "theme hub".
 *
 * Before:
 *   A sido node's parent_region_id resolves to the country root (depth=0),
 *   and the trigger looks for a content_node at that coordinate. We never
 *   create such a node, so sido publishing always failed with
 *   "no parent node".
 *
 * After:
 *   Walk up region.parent. If we encounter a depth=0 region (country root),
 *   treat it as if there were no parent region — fall back to looking up
 *   the theme hub (region_id IS NULL). This matches the strategy doc's
 *   intent: every sido's parent is the theme hub.
 */
final class Version20260506062051 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Parent live check: collapse country-root region to theme hub lookup';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_parent_live_check() RETURNS TRIGGER AS $$
            DECLARE
                parent_region_id INT;
                parent_region_depth INT;
                parent_status VARCHAR(20);
            BEGIN
                IF NEW.status <> 'live' THEN
                    RETURN NEW;
                END IF;

                IF NEW.region_id IS NULL THEN
                    RETURN NEW;
                END IF;

                SELECT parent_id INTO parent_region_id FROM region WHERE id = NEW.region_id;

                -- If parent is the country root (depth=0), treat as no region — look for theme hub.
                IF parent_region_id IS NOT NULL THEN
                    SELECT depth INTO parent_region_depth FROM region WHERE id = parent_region_id;
                    IF parent_region_depth = 0 THEN
                        parent_region_id := NULL;
                    END IF;
                END IF;

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
    }

    public function down(Schema $schema): void
    {
        // Restore the strict version that does not collapse depth=0.
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
    }
}
