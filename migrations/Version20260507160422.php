<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add article body_template gate path.
 *
 * Articles (article_essay, article_column) are editorial prose content. They
 * share guides' body_markdown requirement (>= 3000 visible chars) but do NOT
 * require FAQ DataPoints — articles are essays, not how-to references.
 *
 * Three gate paths now coexist:
 *   - Matrix: data_count >= 5 (region pages, default theme hubs)
 *   - Guide:  body_markdown >= 3000 + verified FAQ >= 3
 *   - Article: body_markdown >= 3000 (no FAQ requirement)
 *
 * Common: intro_text non-empty, verified author.
 */
final class Version20260507160422 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add article gate path to fn_publish_gate (body_markdown only, no FAQ requirement)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_publish_gate() RETURNS TRIGGER AS $$
            DECLARE
                author_verified TIMESTAMP;
                is_guide BOOLEAN;
                is_article BOOLEAN;
                visible_len INT;
                faq_count INT;
            BEGIN
                IF NEW.status <> 'live' THEN
                    RETURN NEW;
                END IF;

                is_guide := NEW.body_template IN ('guide_longform', 'guide_comparison', 'guide_faq');
                is_article := NEW.body_template IN ('article_essay', 'article_column');

                -- Already-live node: auto-demote on threshold breach instead of raising.
                IF TG_OP = 'UPDATE' AND OLD.status = 'live' THEN
                    IF is_guide THEN
                        visible_len := COALESCE(LENGTH(REGEXP_REPLACE(NEW.body_markdown, '\s+', ' ', 'g')), 0);
                        SELECT COUNT(*) INTO faq_count FROM data_point
                            WHERE node_id = NEW.id AND kind = 'faq' AND verified = TRUE;
                        IF visible_len < 3000 OR faq_count < 3 THEN
                            NEW.status := 'noindex';
                        END IF;
                    ELSIF is_article THEN
                        visible_len := COALESCE(LENGTH(REGEXP_REPLACE(NEW.body_markdown, '\s+', ' ', 'g')), 0);
                        IF visible_len < 3000 THEN
                            NEW.status := 'noindex';
                        END IF;
                    ELSE
                        IF NEW.data_count < 5 THEN
                            NEW.status := 'noindex';
                        END IF;
                    END IF;
                    RETURN NEW;
                END IF;

                -- INSERT into live, or transition non-live → live: enforce full gate.
                IF is_guide THEN
                    IF NEW.body_markdown IS NULL OR LENGTH(TRIM(NEW.body_markdown)) = 0 THEN
                        RAISE EXCEPTION 'Guide gate: content_node % requires non-empty body_markdown', NEW.id;
                    END IF;

                    visible_len := LENGTH(REGEXP_REPLACE(NEW.body_markdown, '\s+', ' ', 'g'));
                    IF visible_len < 3000 THEN
                        RAISE EXCEPTION 'Guide gate: content_node % requires body_markdown visible length >= 3000 (have %)', NEW.id, visible_len;
                    END IF;

                    SELECT COUNT(*) INTO faq_count FROM data_point
                        WHERE node_id = NEW.id AND kind = 'faq' AND verified = TRUE;
                    IF faq_count < 3 THEN
                        RAISE EXCEPTION 'Guide gate: content_node % requires >= 3 verified FAQ data points (have %)', NEW.id, faq_count;
                    END IF;
                ELSIF is_article THEN
                    IF NEW.body_markdown IS NULL OR LENGTH(TRIM(NEW.body_markdown)) = 0 THEN
                        RAISE EXCEPTION 'Article gate: content_node % requires non-empty body_markdown', NEW.id;
                    END IF;

                    visible_len := LENGTH(REGEXP_REPLACE(NEW.body_markdown, '\s+', ' ', 'g'));
                    IF visible_len < 3000 THEN
                        RAISE EXCEPTION 'Article gate: content_node % requires body_markdown visible length >= 3000 (have %)', NEW.id, visible_len;
                    END IF;
                ELSE
                    IF NEW.data_count < 5 THEN
                        RAISE EXCEPTION 'Publish gate: content_node % requires data_count >= 5 (have %)', NEW.id, NEW.data_count;
                    END IF;
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
        // Restore the matrix+guide-only gate from Version20260507141702.
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_publish_gate() RETURNS TRIGGER AS $$
            DECLARE
                author_verified TIMESTAMP;
                is_guide BOOLEAN;
                visible_len INT;
                faq_count INT;
            BEGIN
                IF NEW.status <> 'live' THEN
                    RETURN NEW;
                END IF;

                is_guide := NEW.body_template IN ('guide_longform', 'guide_comparison', 'guide_faq');

                IF TG_OP = 'UPDATE' AND OLD.status = 'live' THEN
                    IF is_guide THEN
                        visible_len := COALESCE(LENGTH(REGEXP_REPLACE(NEW.body_markdown, '\s+', ' ', 'g')), 0);
                        SELECT COUNT(*) INTO faq_count FROM data_point
                            WHERE node_id = NEW.id AND kind = 'faq' AND verified = TRUE;
                        IF visible_len < 3000 OR faq_count < 3 THEN
                            NEW.status := 'noindex';
                        END IF;
                    ELSE
                        IF NEW.data_count < 5 THEN
                            NEW.status := 'noindex';
                        END IF;
                    END IF;
                    RETURN NEW;
                END IF;

                IF is_guide THEN
                    IF NEW.body_markdown IS NULL OR LENGTH(TRIM(NEW.body_markdown)) = 0 THEN
                        RAISE EXCEPTION 'Guide gate: content_node % requires non-empty body_markdown', NEW.id;
                    END IF;
                    visible_len := LENGTH(REGEXP_REPLACE(NEW.body_markdown, '\s+', ' ', 'g'));
                    IF visible_len < 3000 THEN
                        RAISE EXCEPTION 'Guide gate: content_node % requires body_markdown visible length >= 3000 (have %)', NEW.id, visible_len;
                    END IF;
                    SELECT COUNT(*) INTO faq_count FROM data_point
                        WHERE node_id = NEW.id AND kind = 'faq' AND verified = TRUE;
                    IF faq_count < 3 THEN
                        RAISE EXCEPTION 'Guide gate: content_node % requires >= 3 verified FAQ data points (have %)', NEW.id, faq_count;
                    END IF;
                ELSE
                    IF NEW.data_count < 5 THEN
                        RAISE EXCEPTION 'Publish gate: content_node % requires data_count >= 5 (have %)', NEW.id, NEW.data_count;
                    END IF;
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
