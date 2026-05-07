<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add report and case_study gate paths.
 *
 * 권위 빌딩용 두 콘텐츠 타입의 발행 요건:
 *   - Report:     body_markdown >= 5000 chars (깊은 분석)
 *   - CaseStudy:  body_markdown >= 1500 chars (구체적 사례)
 *
 * 둘 다 FAQ는 요구 안 함 (가이드의 how-to 가정과 다름).
 * 작성자 검증·intro_text 요건은 기존과 동일.
 *
 * 이제 5개 게이트 경로:
 *   matrix       : data_count >= 5
 *   guide        : body_markdown >= 3000 + verified FAQ >= 3
 *   article      : body_markdown >= 3000
 *   report       : body_markdown >= 5000
 *   case_study   : body_markdown >= 1500
 */
final class Version20260507161808 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add report (>=5000) and case_study (>=1500) gate paths to fn_publish_gate';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_publish_gate() RETURNS TRIGGER AS $$
            DECLARE
                author_verified TIMESTAMP;
                gate_kind VARCHAR(20);
                min_len INT;
                require_faq BOOLEAN;
                visible_len INT;
                faq_count INT;
            BEGIN
                IF NEW.status <> 'live' THEN
                    RETURN NEW;
                END IF;

                -- Determine which gate path applies + its parameters
                CASE NEW.body_template
                    WHEN 'guide_longform','guide_comparison','guide_faq' THEN
                        gate_kind := 'guide';
                        min_len := 3000;
                        require_faq := TRUE;
                    WHEN 'article_essay','article_column' THEN
                        gate_kind := 'article';
                        min_len := 3000;
                        require_faq := FALSE;
                    WHEN 'report' THEN
                        gate_kind := 'report';
                        min_len := 5000;
                        require_faq := FALSE;
                    WHEN 'case_study' THEN
                        gate_kind := 'case_study';
                        min_len := 1500;
                        require_faq := FALSE;
                    ELSE
                        gate_kind := 'matrix';
                        min_len := 0;
                        require_faq := FALSE;
                END CASE;

                -- Already-live node: auto-demote on threshold breach instead of raising.
                IF TG_OP = 'UPDATE' AND OLD.status = 'live' THEN
                    IF gate_kind = 'matrix' THEN
                        IF NEW.data_count < 5 THEN
                            NEW.status := 'noindex';
                        END IF;
                    ELSE
                        visible_len := COALESCE(LENGTH(REGEXP_REPLACE(NEW.body_markdown, '\s+', ' ', 'g')), 0);
                        IF visible_len < min_len THEN
                            NEW.status := 'noindex';
                        ELSIF require_faq THEN
                            SELECT COUNT(*) INTO faq_count FROM data_point
                                WHERE node_id = NEW.id AND kind = 'faq' AND verified = TRUE;
                            IF faq_count < 3 THEN
                                NEW.status := 'noindex';
                            END IF;
                        END IF;
                    END IF;
                    RETURN NEW;
                END IF;

                -- INSERT into live, or transition non-live → live: enforce full gate.
                IF gate_kind = 'matrix' THEN
                    IF NEW.data_count < 5 THEN
                        RAISE EXCEPTION 'Publish gate (matrix): content_node % requires data_count >= 5 (have %)', NEW.id, NEW.data_count;
                    END IF;
                ELSE
                    IF NEW.body_markdown IS NULL OR LENGTH(TRIM(NEW.body_markdown)) = 0 THEN
                        RAISE EXCEPTION 'Publish gate (%): content_node % requires non-empty body_markdown', gate_kind, NEW.id;
                    END IF;

                    visible_len := LENGTH(REGEXP_REPLACE(NEW.body_markdown, '\s+', ' ', 'g'));
                    IF visible_len < min_len THEN
                        RAISE EXCEPTION 'Publish gate (%): content_node % requires body_markdown visible length >= % (have %)', gate_kind, NEW.id, min_len, visible_len;
                    END IF;

                    IF require_faq THEN
                        SELECT COUNT(*) INTO faq_count FROM data_point
                            WHERE node_id = NEW.id AND kind = 'faq' AND verified = TRUE;
                        IF faq_count < 3 THEN
                            RAISE EXCEPTION 'Publish gate (%): content_node % requires >= 3 verified FAQ data points (have %)', gate_kind, NEW.id, faq_count;
                        END IF;
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
        // Restore the 3-path version from Version20260507160422 (matrix + guide + article).
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

                IF is_guide THEN
                    visible_len := LENGTH(REGEXP_REPLACE(NEW.body_markdown, '\s+', ' ', 'g'));
                    IF visible_len < 3000 THEN
                        RAISE EXCEPTION 'Guide gate fail';
                    END IF;
                    SELECT COUNT(*) INTO faq_count FROM data_point
                        WHERE node_id = NEW.id AND kind = 'faq' AND verified = TRUE;
                    IF faq_count < 3 THEN
                        RAISE EXCEPTION 'Guide FAQ count fail';
                    END IF;
                ELSIF is_article THEN
                    visible_len := LENGTH(REGEXP_REPLACE(NEW.body_markdown, '\s+', ' ', 'g'));
                    IF visible_len < 3000 THEN
                        RAISE EXCEPTION 'Article gate fail';
                    END IF;
                ELSE
                    IF NEW.data_count < 5 THEN
                        RAISE EXCEPTION 'Matrix gate fail';
                    END IF;
                END IF;

                IF NEW.intro_text IS NULL THEN RAISE EXCEPTION 'intro_text required'; END IF;
                IF NEW.author_id IS NULL THEN RAISE EXCEPTION 'author required'; END IF;
                SELECT verified_at INTO author_verified FROM author WHERE id = NEW.author_id;
                IF author_verified IS NULL THEN RAISE EXCEPTION 'verified author required'; END IF;
                IF NEW.first_published_at IS NULL THEN NEW.first_published_at := NOW(); END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);
    }
}
