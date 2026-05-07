<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Consolidate article_essay + article_column → single 'essay' body_template.
 *
 * 변종 구분(에세이/칼럼)은 시각적·운영적 부담만 늘리고 의미 있는 차이를 만들지 못해
 * 단일 'essay' 타입으로 통일. 기존 노드 데이터를 일괄 마이그레이트하고 게이트의
 * 분기 룩업도 단순화.
 *
 * 게이트 요건은 article과 동일: body_markdown >= 3000, FAQ 요구 안 함.
 */
final class Version20260507163246 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Consolidate article_essay/article_column → essay; update fn_publish_gate';
    }

    public function up(Schema $schema): void
    {
        // 순서 주의: 게이트 함수를 먼저 교체한 뒤 row를 업데이트.
        // 반대로 하면 옛 트리거가 'essay'를 모르는 값으로 보고 matrix 게이트로 빠져
        // data_count < 5인 에세이 노드들이 자동으로 noindex로 강등됨.

        // Step 1: 게이트의 article 분기를 essay로 교체
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

                CASE NEW.body_template
                    WHEN 'guide_longform','guide_comparison','guide_faq' THEN
                        gate_kind := 'guide';
                        min_len := 3000;
                        require_faq := TRUE;
                    WHEN 'essay' THEN
                        gate_kind := 'essay';
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

        // Step 2: 기존 노드 body_template 일괄 변환 (이제 새 게이트가 essay를 인식함)
        $this->addSql(<<<'SQL'
            UPDATE content_node
            SET body_template = 'essay'
            WHERE body_template IN ('article_essay', 'article_column')
        SQL);
    }

    public function down(Schema $schema): void
    {
        // 노드 데이터를 article_essay로 되돌림 (column 정보는 손실됨 — irreversible).
        $this->addSql(<<<'SQL'
            UPDATE content_node
            SET body_template = 'article_essay'
            WHERE body_template = 'essay'
        SQL);

        // Restore the article-split version from Version20260507161808.
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
                IF NEW.status <> 'live' THEN RETURN NEW; END IF;
                CASE NEW.body_template
                    WHEN 'guide_longform','guide_comparison','guide_faq' THEN
                        gate_kind := 'guide'; min_len := 3000; require_faq := TRUE;
                    WHEN 'article_essay','article_column' THEN
                        gate_kind := 'article'; min_len := 3000; require_faq := FALSE;
                    WHEN 'report' THEN
                        gate_kind := 'report'; min_len := 5000; require_faq := FALSE;
                    WHEN 'case_study' THEN
                        gate_kind := 'case_study'; min_len := 1500; require_faq := FALSE;
                    ELSE
                        gate_kind := 'matrix'; min_len := 0; require_faq := FALSE;
                END CASE;

                IF TG_OP = 'UPDATE' AND OLD.status = 'live' THEN
                    IF gate_kind = 'matrix' THEN
                        IF NEW.data_count < 5 THEN NEW.status := 'noindex'; END IF;
                    ELSE
                        visible_len := COALESCE(LENGTH(REGEXP_REPLACE(NEW.body_markdown, '\s+', ' ', 'g')), 0);
                        IF visible_len < min_len THEN NEW.status := 'noindex';
                        ELSIF require_faq THEN
                            SELECT COUNT(*) INTO faq_count FROM data_point WHERE node_id = NEW.id AND kind = 'faq' AND verified = TRUE;
                            IF faq_count < 3 THEN NEW.status := 'noindex'; END IF;
                        END IF;
                    END IF;
                    RETURN NEW;
                END IF;

                IF gate_kind = 'matrix' THEN
                    IF NEW.data_count < 5 THEN RAISE EXCEPTION 'matrix gate fail'; END IF;
                ELSE
                    visible_len := LENGTH(REGEXP_REPLACE(NEW.body_markdown, '\s+', ' ', 'g'));
                    IF visible_len < min_len THEN RAISE EXCEPTION 'gate fail'; END IF;
                    IF require_faq THEN
                        SELECT COUNT(*) INTO faq_count FROM data_point WHERE node_id = NEW.id AND kind = 'faq' AND verified = TRUE;
                        IF faq_count < 3 THEN RAISE EXCEPTION 'faq fail'; END IF;
                    END IF;
                END IF;

                IF NEW.intro_text IS NULL THEN RAISE EXCEPTION 'intro required'; END IF;
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
