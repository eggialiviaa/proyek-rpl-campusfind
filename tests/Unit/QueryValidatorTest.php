<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\CFind\QueryValidator;
use App\Services\CFind\SemanticSchema;
use PHPUnit\Framework\TestCase;

class QueryValidatorTest extends TestCase
{
    private QueryValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $schema = new SemanticSchema();
        $this->validator = new QueryValidator($schema);
    }

    public function testRejectsNonArrayOrMissingQueries(): void
    {
        $plan = ['invalid' => true];
        $res = $this->validator->validate($plan, 'civitas');

        $this->assertFalse($res['valid']);
        $this->assertSame('invalid_schema', $res['reason']);
    }

    public function testRejectsEmptyQueries(): void
    {
        $plan = ['queries' => []];
        $res = $this->validator->validate($plan, 'civitas');

        $this->assertFalse($res['valid']);
        $this->assertSame('empty_queries', $res['reason']);
    }

    public function testRejectsExcessiveQueriesOverThree(): void
    {
        $plan = [
            'queries' => [
                ['entity' => 'categories'],
                ['entity' => 'locations'],
                ['entity' => 'public_catalog'],
                ['entity' => 'my_claims'],
            ],
        ];

        $res = $this->validator->validate($plan, 'civitas');
        $this->assertFalse($res['valid']);
        $this->assertSame('limit_exceeded', $res['reason']);
    }

    public function testRejectsMissingEntity(): void
    {
        $plan = [
            'queries' => [
                ['select' => ['id', 'name']],
            ],
        ];

        $res = $this->validator->validate($plan, 'civitas');
        $this->assertFalse($res['valid']);
        $this->assertSame('invalid_schema', $res['reason']);
    }

    public function testRejectsGuestAccessToDataEntities(): void
    {
        $plan = [
            'queries' => [
                ['entity' => 'categories', 'select' => ['id', 'name']],
            ],
        ];

        $res = $this->validator->validate($plan, 'guest');
        $this->assertFalse($res['valid']);
        $this->assertSame('denied_role', $res['reason']);
    }

    public function testRejectsUnauthorizedEntityForCivitas(): void
    {
        // civitas cannot access audit_logs or posko_items
        $plan = [
            'queries' => [
                ['entity' => 'posko_items', 'select' => ['id', 'item_code']],
            ],
        ];

        $res = $this->validator->validate($plan, 'civitas');
        $this->assertFalse($res['valid']);
        $this->assertSame('denied_role', $res['reason']);
    }

    public function testRejectsUnknownEntity(): void
    {
        // Make custom schema or test non-existent entity for admin who has broad permissions
        $plan = [
            'queries' => [
                ['entity' => 'non_existent_entity_xyz', 'select' => ['id']],
            ],
        ];

        $res = $this->validator->validate($plan, 'admin');
        $this->assertFalse($res['valid']);
        // If entity is not in allowedEntities for admin, reason is denied_role or unknown_entity
        $this->assertContains($res['reason'], ['denied_role', 'unknown_entity']);
    }

    public function testRejectsForbiddenFieldsInSelect(): void
    {
        $plan = [
            'queries' => [
                [
                    'entity' => 'my_lost_reports',
                    'select' => ['id', 'title', 'password'],
                ],
            ],
        ];

        $res = $this->validator->validate($plan, 'civitas');
        $this->assertFalse($res['valid']);
        $this->assertSame('forbidden_field', $res['reason']);
    }

    public function testValidQueryPlanPasses(): void
    {
        $plan = [
            'queries' => [
                [
                    'entity' => 'categories',
                    'select' => ['id', 'name'],
                    'limit'  => 10,
                ],
            ],
        ];

        $res = $this->validator->validate($plan, 'civitas');
        $this->assertTrue($res['valid']);
        $this->assertArrayHasKey('validated_plan', $res);
        $this->assertCount(1, $res['validated_plan']['queries']);
        $this->assertSame('categories', $res['validated_plan']['queries'][0]['entity']);
    }

    public function testCapMaxLimitToFifty(): void
    {
        $plan = [
            'queries' => [
                [
                    'entity' => 'categories',
                    'select' => ['id', 'name'],
                    'limit'  => 100, // Should be capped to 50
                ],
            ],
        ];

        $res = $this->validator->validate($plan, 'civitas');
        $this->assertTrue($res['valid']);
        $this->assertLessThanOrEqual(50, $res['validated_plan']['queries'][0]['limit']);
    }
}
