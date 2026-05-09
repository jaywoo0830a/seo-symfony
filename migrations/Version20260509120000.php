<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * BodyTemplate enum 정리 — 10케이스 → 5케이스.
 *
 * Before: 한 enum이 세 가지 축(매트릭스 region depth / 가이드 sub-kind / 페이지 패밀리)을
 *         섞어 담고 있었음.
 * After:  BodyTemplate 5개만 (matrix, guide, essay, report, case_study). 매트릭스 region
 *         변주는 region.depth에서 파생, 가이드 안의 sub-kind는 의도적으로 두지 않음.
 *
 * 처리:
 *   1. 기존 row 마이그레이트:
 *        guide_longform | guide_comparison | guide_faq → guide
 *        hub | sido | sigungu | dong → matrix
 *   2. fn_publish_gate 트리거를 새 enum 값에 맞춰 교체.
 */
final class Version20260509120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Collapse 10-case BodyTemplate to 5; update fn_publish_gate';
    }

    public function up(Schema $schema): void
    {
        // 트리거를 먼저 교체 — 그래야 row UPDATE 시 새 enum 값을 정상 인식.
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
                    WHEN 'guide' THEN
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

        // 가이드 sub-kind 통합. 변종(longform/comparison/faq) 정보는 의도적으로 버림 —
        // sub-kind 자체를 삭제하는 변경.
        $this->addSql(<<<'SQL'
            UPDATE content_node
            SET body_template = 'guide'
            WHERE body_template IN ('guide_longform', 'guide_comparison', 'guide_faq')
        SQL);

        // 매트릭스 row는 명시적인 'matrix'로 통합 (이전엔 hub/sido/sigungu/dong).
        $this->addSql(<<<'SQL'
            UPDATE content_node
            SET body_template = 'matrix'
            WHERE body_template IN ('hub', 'sido', 'sigungu', 'dong')
        SQL);
    }

    public function down(Schema $schema): void
    {
        // 가이드는 sub-kind 정보가 손실됐으므로 'guide_longform'을 best-effort 기본값으로.
        $this->addSql(<<<'SQL'
            UPDATE content_node
            SET body_template = 'guide_longform'
            WHERE body_template = 'guide'
        SQL);

        // 매트릭스 row는 NULL로 되돌림 (이전엔 region.depth로 파생).
        $this->addSql(<<<'SQL'
            UPDATE content_node
            SET body_template = NULL
            WHERE body_template = 'matrix'
        SQL);

        // 트리거를 옛 분기로 복원 (Version20260507163246의 up() 본문).
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
    }
}
