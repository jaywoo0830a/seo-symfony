<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 0 시드 — theme 4 + region 49 + author 1.
 *
 * 이전엔 ThemeFixtures / RegionFixtures / AuthorFixtures (DoctrineFixturesBundle)가
 * 처리. 운영(prod) 환경에선 fixtures bundle 미설치 → 시드 불가능했고,
 * 매번 우회 작업(임시 dev install) 필요. migration 으로 옮겨 dev/prod 모두 동일하게
 * `migrate` 한 번으로 시드 끝.
 *
 * 모든 INSERT 는 WHERE NOT EXISTS 가드로 idempotent — 기존 데이터 있어도 안전.
 *
 * 시드 내용:
 *   theme   : 4 root themes — tutoring(매트릭스) + guides/reports/cases(prose 컨테이너)
 *   region  : 1 KR + 17 시도 + 25 서울 자치구 + 6 강남 동
 *   author  : 1 placeholder (verified_at=NULL — 운영 시작 전 admin 에서 본인 정보로 교체 필수)
 */
final class Version20260510094000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 0 seed: 4 themes + 49 regions + 1 author placeholder';
    }

    public function up(Schema $schema): void
    {
        // ── Themes (4 roots) ──────────────────────────────────────────
        // theme.created_at is NOT NULL with no DB default — must specify NOW().
        $this->addSql(<<<SQL
            INSERT INTO theme (slug, name, depth, description, created_at)
            SELECT v.slug, v.name, 0, v.description, NOW()
            FROM (VALUES
                ('tutoring', '초등 과외',     '초등 1~6학년 전 과목 1:1 학습. 지역별 학습 정보가 모이는 매트릭스 테마.'),
                ('guides',   '학습 가이드',   '학년별 학습 로드맵, 과목별 자기주도 학습법 등 정보형 가이드.'),
                ('reports',  '데이터 리포트', '자체 수집·정리한 초등 학습 데이터 리포트.'),
                ('cases',    '사례 연구',     '익명화한 실제 학습 변화 사례.')
            ) AS v(slug, name, description)
            WHERE NOT EXISTS (
                SELECT 1 FROM theme t WHERE t.slug = v.slug AND t.parent_id IS NULL
            )
        SQL);

        // ── Region: KR root (depth 0) ─────────────────────────────────
        $this->addSql(<<<SQL
            INSERT INTO region (slug, name, depth)
            SELECT 'kr', '대한민국', 0
            WHERE NOT EXISTS (
                SELECT 1 FROM region WHERE slug = 'kr' AND depth = 0
            )
        SQL);

        // ── Region: 17 시도 (depth 1, parent = kr) ────────────────────
        $this->addSql(<<<SQL
            INSERT INTO region (slug, name, depth, parent_id, admin_code)
            SELECT v.slug, v.name, 1, kr.id, v.code
            FROM (VALUES
                ('seoul',     '서울특별시',       '11'),
                ('busan',     '부산광역시',       '26'),
                ('daegu',     '대구광역시',       '27'),
                ('incheon',   '인천광역시',       '28'),
                ('gwangju',   '광주광역시',       '29'),
                ('daejeon',   '대전광역시',       '30'),
                ('ulsan',     '울산광역시',       '31'),
                ('sejong',    '세종특별자치시',   '36'),
                ('gyeonggi',  '경기도',           '41'),
                ('gangwon',   '강원특별자치도',   '51'),
                ('chungbuk',  '충청북도',         '43'),
                ('chungnam',  '충청남도',         '44'),
                ('jeonbuk',   '전북특별자치도',   '52'),
                ('jeonnam',   '전라남도',         '46'),
                ('gyeongbuk', '경상북도',         '47'),
                ('gyeongnam', '경상남도',         '48'),
                ('jeju',      '제주특별자치도',   '50')
            ) AS v(slug, name, code)
            CROSS JOIN (SELECT id FROM region WHERE slug = 'kr' AND depth = 0 LIMIT 1) AS kr
            WHERE NOT EXISTS (
                SELECT 1 FROM region r WHERE r.slug = v.slug AND r.parent_id = kr.id
            )
        SQL);

        // ── Region: 25 서울 자치구 (depth 2, parent = seoul) ──────────
        $this->addSql(<<<SQL
            INSERT INTO region (slug, name, depth, parent_id, admin_code)
            SELECT v.slug, v.name, 2, seoul.id, v.code
            FROM (VALUES
                ('jongno-gu',       '종로구',     '11110'),
                ('jung-gu',         '중구',       '11140'),
                ('yongsan-gu',      '용산구',     '11170'),
                ('seongdong-gu',    '성동구',     '11200'),
                ('gwangjin-gu',     '광진구',     '11215'),
                ('dongdaemun-gu',   '동대문구',   '11230'),
                ('jungnang-gu',     '중랑구',     '11260'),
                ('seongbuk-gu',     '성북구',     '11290'),
                ('gangbuk-gu',      '강북구',     '11305'),
                ('dobong-gu',       '도봉구',     '11320'),
                ('nowon-gu',        '노원구',     '11350'),
                ('eunpyeong-gu',    '은평구',     '11380'),
                ('seodaemun-gu',    '서대문구',   '11410'),
                ('mapo-gu',         '마포구',     '11440'),
                ('yangcheon-gu',    '양천구',     '11470'),
                ('gangseo-gu',      '강서구',     '11500'),
                ('guro-gu',         '구로구',     '11530'),
                ('geumcheon-gu',    '금천구',     '11545'),
                ('yeongdeungpo-gu', '영등포구',   '11560'),
                ('dongjak-gu',      '동작구',     '11590'),
                ('gwanak-gu',       '관악구',     '11620'),
                ('seocho-gu',       '서초구',     '11650'),
                ('gangnam-gu',      '강남구',     '11680'),
                ('songpa-gu',       '송파구',     '11710'),
                ('gangdong-gu',     '강동구',     '11740')
            ) AS v(slug, name, code)
            CROSS JOIN (SELECT id FROM region WHERE slug = 'seoul' AND depth = 1 LIMIT 1) AS seoul
            WHERE NOT EXISTS (
                SELECT 1 FROM region r WHERE r.slug = v.slug AND r.parent_id = seoul.id
            )
        SQL);

        // ── Region: 6 강남 동 (depth 3, parent = gangnam-gu) ──────────
        $this->addSql(<<<SQL
            INSERT INTO region (slug, name, depth, parent_id, admin_code)
            SELECT v.slug, v.name, 3, gn.id, v.code
            FROM (VALUES
                ('apgujeong-dong',  '압구정동', '1168010100'),
                ('cheongdam-dong',  '청담동',   '1168010400'),
                ('samseong-dong',   '삼성동',   '1168010500'),
                ('daechi-dong',     '대치동',   '1168010600'),
                ('yeoksam-dong',    '역삼동',   '1168010100'),
                ('nonhyeon-dong',   '논현동',   '1168010800')
            ) AS v(slug, name, code)
            CROSS JOIN (SELECT id FROM region WHERE slug = 'gangnam-gu' AND depth = 2 LIMIT 1) AS gn
            WHERE NOT EXISTS (
                SELECT 1 FROM region r WHERE r.slug = v.slug AND r.parent_id = gn.id
            )
        SQL);

        // ── Author placeholder (verified_at = NULL = guardrail) ───────
        $this->addSql(<<<SQL
            INSERT INTO author (slug, real_name, credentials, bio)
            SELECT
                'sales-rep',
                '(본인 실명 입력 필요)',
                '초등 학습 상담 파트너 — 자격·경력 입력 필요',
                '(소개글 입력 필요. Phase 1 시작 전 실명·경력으로 교체 후 verify.)'
            WHERE NOT EXISTS (
                SELECT 1 FROM author WHERE slug = 'sales-rep'
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        // 역순 — author → region(자식부터) → theme.
        // 운영 데이터(ContentNode, DataPoint)가 이 row 들을 FK 참조하므로,
        // 실제 다운그레이드 전엔 ContentNode 정리 필요 (RESTRICT).
        $this->addSql("DELETE FROM author WHERE slug = 'sales-rep'");
        $this->addSql("DELETE FROM region WHERE depth = 3 AND parent_id IN (SELECT id FROM region WHERE slug = 'gangnam-gu' AND depth = 2)");
        $this->addSql("DELETE FROM region WHERE depth = 2 AND parent_id IN (SELECT id FROM region WHERE slug = 'seoul' AND depth = 1)");
        $this->addSql("DELETE FROM region WHERE depth = 1 AND parent_id IN (SELECT id FROM region WHERE slug = 'kr' AND depth = 0)");
        $this->addSql("DELETE FROM region WHERE slug = 'kr' AND depth = 0");
        $this->addSql("DELETE FROM theme WHERE slug IN ('tutoring', 'guides', 'reports', 'cases') AND parent_id IS NULL");
    }
}
