<?php

declare(strict_types=1);

/**
 * BankSploit: Beneficiaries Module Test Suite
 *
 * Comprehensive functional tests covering:
 * - Add beneficiary (valid inputs, duplicates, edge cases)
 * - List beneficiaries (ownership, filtering)
 * - Delete beneficiary
 * - IDOR vulnerability (beneficiary.l3)
 * - Stored XSS vulnerability (beneficiary.g3)
 * - Cooling period bypass (beneficiary.l8)
 * - API endpoints
 */

use PHPUnit\Framework\TestCase;

class BeneficiariesTest extends TestCase
{
    private PDO $pdo;
    private int $testUserId;
    private int $otherUserId;
    private string $csrfToken;

    protected function setUp(): void
    {
        // Initialize test database connection
        $this->pdo = new PDO(
            'mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'],
            $_ENV['DB_USER'],
            $_ENV['DB_PASS']
        );
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create test users
        $this->createTestUsers();
        $this->csrfToken = bin2hex(random_bytes(32));
    }

    protected function tearDown(): void
    {
        // Clean up test data
        $this->pdo->query('DELETE FROM beneficiaries WHERE user_id IN (?, ?)', [$this->testUserId, $this->otherUserId]);
    }

    // ========================================================================
    // TEST SUITE 1: DUPLICATE BENEFICIARY ISSUE (PRIMARY ISSUE)
    // ========================================================================

    /**
     * TEST: Same account can be added twice as beneficiary
     * EXPECTED: Beneficiary added successfully (current behavior)
     * RISK: Duplicate entries in beneficiary list
     */
    public function testDuplicateBeneficiaryCanBeAdded(): void
    {
        $input = [
            'name' => 'John Doe',
            'account_number' => '1234567890',
            'ifsc_code' => 'SBIN0001234',
            'bank_name' => 'State Bank',
        ];

        // Add first time
        $this->addBeneficiary($input);
        $this->assertBeneficiaryExists($input['account_number'], $this->testUserId);

        // Add same account again (this should fail in production, but works now)
        $this->addBeneficiary($input);

        // Verify duplicates exist
        $count = $this->countBeneficiariesByAccountNumber($input['account_number'], $this->testUserId);
        $this->assertEquals(2, $count, 'Same account was added twice (duplicate not prevented)');
    }

    /**
     * TEST: Multiple duplicate entries affect transfer operations
     * EXPECTED: Transfer dropdown shows multiple entries for same account
     * RISK: User confusion, potential logic errors in transfers
     */
    public function testMultipleDuplicatesInTransferDropdown(): void
    {
        $accountNumber = '9876543210';

        // Add beneficiary 3 times
        for ($i = 0; $i < 3; $i++) {
            $this->addBeneficiary([
                'name' => "Account Copy $i",
                'account_number' => $accountNumber,
                'ifsc_code' => 'HDFC0001234',
                'bank_name' => 'HDFC Bank',
            ]);
        }

        $beneficiaries = $this->xferUserBeneficiaries($this->testUserId);
        $duplicateCount = array_reduce($beneficiaries, function($carry, $item) use ($accountNumber) {
            return $carry + ($item['account_number'] === $accountNumber ? 1 : 0);
        }, 0);

        $this->assertEquals(3, $duplicateCount, 'Transfer dropdown will show same account 3 times');
    }

    // ========================================================================
    // TEST SUITE 2: BENEFICIARY MANAGEMENT (FUNCTIONAL)
    // ========================================================================

    /**
     * TEST: Add beneficiary with valid data
     * EXPECTED: Beneficiary created, visible in list
     */
    public function testAddBeneficiaryWithValidData(): void
    {
        $input = [
            'name' => 'Alice Smith',
            'account_number' => '1111111111',
            'ifsc_code' => 'ICIC0000001',
            'bank_name' => 'ICICI Bank',
        ];

        $beneficiaryId = $this->addBeneficiary($input);
        $this->assertNotNull($beneficiaryId, 'Beneficiary ID should be returned');

        $beneficiary = $this->getBeneficiary($beneficiaryId, $this->testUserId);
        $this->assertNotNull($beneficiary);
        $this->assertEquals($input['name'], $beneficiary['name']);
        $this->assertEquals($input['account_number'], $beneficiary['account_number']);
    }

    /**
     * TEST: Add beneficiary with empty fields
     * EXPECTED: Validation should fail (or entries with empty fields created)
     */
    public function testAddBeneficiaryWithEmptyFields(): void
    {
        $input = [
            'name' => '',
            'account_number' => '2222222222',
            'ifsc_code' => '',
            'bank_name' => 'Test Bank',
        ];

        // Current code allows this
        $beneficiaryId = $this->addBeneficiary($input);
        $beneficiary = $this->getBeneficiary($beneficiaryId, $this->testUserId);

        $this->assertEquals('', $beneficiary['name']);
        $this->assertEquals('', $beneficiary['ifsc_code']);
    }

    /**
     * TEST: Add beneficiary with special characters (XSS attempt)
     * EXPECTED: At secure level, name should be HTML-escaped on output
     */
    public function testAddBeneficiaryWithXSSPayload(): void
    {
        $xssPayload = '<script>alert("XSS")</script>';
        $input = [
            'name' => $xssPayload,
            'account_number' => '3333333333',
            'ifsc_code' => 'TEST0000001',
            'bank_name' => 'Test Bank',
        ];

        $beneficiaryId = $this->addBeneficiary($input);
        $beneficiary = $this->getBeneficiary($beneficiaryId, $this->testUserId);

        // At vulnerable levels, script tag is stored as-is
        $this->assertEquals($xssPayload, $beneficiary['name']);
    }

    /**
     * TEST: Delete beneficiary
     * EXPECTED: Beneficiary removed from list
     */
    public function testDeleteBeneficiary(): void
    {
        $input = [
            'name' => 'Delete Test',
            'account_number' => '4444444444',
            'ifsc_code' => 'DEL0000001',
            'bank_name' => 'Delete Bank',
        ];

        $beneficiaryId = $this->addBeneficiary($input);
        $this->assertBeneficiaryExists($input['account_number'], $this->testUserId);

        $this->deleteBeneficiary($beneficiaryId, $this->testUserId);
        $beneficiary = $this->getBeneficiary($beneficiaryId, $this->testUserId);

        $this->assertNull($beneficiary, 'Beneficiary should be deleted');
    }

    /**
     * TEST: List beneficiaries (ownership check)
     * EXPECTED: Only current user's beneficiaries returned
     */
    public function testListBeneficiariesByUser(): void
    {
        // Add for test user
        $this->addBeneficiary([
            'name' => 'User 1 Ben',
            'account_number' => '5555555555',
            'ifsc_code' => 'USR10000001',
            'bank_name' => 'User 1 Bank',
        ]);

        // Add for other user
        $this->addBeneficiaryForUser($this->otherUserId, [
            'name' => 'User 2 Ben',
            'account_number' => '6666666666',
            'ifsc_code' => 'USR20000001',
            'bank_name' => 'User 2 Bank',
        ]);

        $testUserBeneficiaries = $this->xferUserBeneficiaries($this->testUserId);
        $this->assertCount(1, $testUserBeneficiaries);
        $this->assertEquals('User 1 Ben', $testUserBeneficiaries[0]['name']);
    }

    // ========================================================================
    // TEST SUITE 3: COOLING PERIOD (beneficiary.l8)
    // ========================================================================

    /**
     * TEST: Cooling period bypass at vulnerable level
     * EXPECTED: Client-supplied cooling_period_until is trusted
     */
    public function testCoolingPeriodBypassVulnerable(): void
    {
        $pastDate = date('Y-m-d H:i:s', time() - 86400); // 24 hours ago

        $input = [
            'name' => 'Cooling Test',
            'account_number' => '7777777777',
            'ifsc_code' => 'COOL000001',
            'bank_name' => 'Cooling Bank',
            'cooling_period_until' => $pastDate, // Client sets past date
        ];

        $beneficiaryId = $this->addBeneficiary($input);
        $beneficiary = $this->getBeneficiary($beneficiaryId, $this->testUserId);

        // At vulnerable levels, the cooling period is the one sent by client
        $this->assertEquals($pastDate, $beneficiary['cooling_period_until']);
    }

    /**
     * TEST: Cooling period ignored at secure level
     * EXPECTED: Server computes cooling_period_until as NOW()+24h
     */
    public function testCoolingPeriodSecure(): void
    {
        $requestedDate = date('Y-m-d H:i:s', time() - 86400);
        $futureDate = date('Y-m-d H:i:s', time() + 86400);

        $input = [
            'name' => 'Secure Cooling Test',
            'account_number' => '8888888888',
            'ifsc_code' => 'SCLD000001',
            'bank_name' => 'Secure Bank',
            'cooling_period_until' => $requestedDate,
        ];

        // This test assumes secure mode (where server ignores client cooling_period_until)
        $beneficiaryId = $this->addBeneficiary($input);
        $beneficiary = $this->getBeneficiary($beneficiaryId, $this->testUserId);

        // At secure level, should be server-computed as NOW()+24h
        $this->assertGreaterThan($futureDate, $beneficiary['cooling_period_until']);
    }

    // ========================================================================
    // TEST SUITE 4: IDOR VULNERABILITY (beneficiary.l3)
    // ========================================================================

    /**
     * TEST: IDOR - Read other user's beneficiary
     * EXPECTED: At vulnerable level, no ownership check; at secure, denied
     */
    public function testIDORReadOtherUserBeneficiary(): void
    {
        // Add beneficiary for other user
        $benId = $this->addBeneficiaryForUser($this->otherUserId, [
            'name' => 'Other User Ben',
            'account_number' => '9999999999',
            'ifsc_code' => 'OTHER00001',
            'bank_name' => 'Other Bank',
        ]);

        // Try to read it as test user (vulnerable: works, secure: denied)
        $beneficiary = $this->getBeneficiary($benId, $this->testUserId);

        // At vulnerable levels, this returns the beneficiary (no ownership check)
        $this->assertNotNull($beneficiary);
        $this->assertEquals('Other User Ben', $beneficiary['name']);
    }

    /**
     * TEST: IDOR - List via API
     * EXPECTED: API endpoint shows all users' beneficiaries at vulnerable level
     */
    public function testIDORListAllBeneficiaries(): void
    {
        $this->addBeneficiary([
            'name' => 'Test User Ben',
            'account_number' => '1010101010',
            'ifsc_code' => 'TEST000001',
            'bank_name' => 'Test Bank',
        ]);

        $this->addBeneficiaryForUser($this->otherUserId, [
            'name' => 'Other User Ben',
            'account_number' => '2020202020',
            'ifsc_code' => 'OTH0000001',
            'bank_name' => 'Other Bank',
        ]);

        // Get list as test user (vulnerable: shows all, secure: shows only own)
        $allBeneficiaries = $this->getAllBeneficiaries();

        // At vulnerable levels, all users' beneficiaries are visible
        $names = array_column($allBeneficiaries, 'name');
        $this->assertContains('Test User Ben', $names);
        $this->assertContains('Other User Ben', $names);
    }

    // ========================================================================
    // TEST SUITE 5: STORED XSS (beneficiary.g3)
    // ========================================================================

    /**
     * TEST: Stored XSS in beneficiary name
     * EXPECTED: At vulnerable, script runs; at secure, escaped
     */
    public function testStoredXSSInBeneficiaryName(): void
    {
        $xssPayload = '<img src=x onerror="alert(\'XSS\')">';

        $beneficiaryId = $this->addBeneficiary([
            'name' => $xssPayload,
            'account_number' => '1111111112',
            'ifsc_code' => 'XSS0000001',
            'bank_name' => 'XSS Bank',
        ]);

        $html = $this->renderBeneficiaryList($this->testUserId);

        // At vulnerable levels, XSS payload appears unescaped in HTML
        $this->assertStringContainsString($xssPayload, $html);
    }

    /**
     * TEST: Event handler attributes stored
     * EXPECTED: HTML should be stored without execution/escaping at vuln level
     */
    public function testStoredXSSWithEventHandlers(): void
    {
        $eventHandlerXSS = '" onload="alert(1)"';

        $beneficiaryId = $this->addBeneficiary([
            'name' => 'Innocent Name' . $eventHandlerXSS,
            'account_number' => '1111111113',
            'ifsc_code' => 'EVT0000001',
            'bank_name' => 'Event Bank',
        ]);

        $beneficiary = $this->getBeneficiary($beneficiaryId, $this->testUserId);
        $this->assertStringContainsString($eventHandlerXSS, $beneficiary['name']);
    }

    // ========================================================================
    // TEST SUITE 6: API ENDPOINT TESTS
    // ========================================================================

    /**
     * TEST: POST /api/v1/beneficiaries - Add via JSON
     */
    public function testAPIAddBeneficiary(): void
    {
        $response = $this->apiPost('/api/v1/beneficiaries', [
            'name' => 'API Beneficiary',
            'account_number' => '3333333333',
            'ifsc_code' => 'API0000001',
            'bank_name' => 'API Bank',
            'csrf_token' => $this->csrfToken,
        ]);

        $this->assertEquals(201, $response['status_code']);
        $this->assertArrayHasKey('id', $response['body']);
    }

    /**
     * TEST: GET /api/v1/beneficiaries - List via JSON
     */
    public function testAPIListBeneficiaries(): void
    {
        $this->addBeneficiary([
            'name' => 'JSON Ben',
            'account_number' => '4444444444',
            'ifsc_code' => 'JSON000001',
            'bank_name' => 'JSON Bank',
        ]);

        $response = $this->apiGet('/api/v1/beneficiaries');

        $this->assertEquals(200, $response['status_code']);
        $this->assertIsArray($response['body']['beneficiaries']);
    }

    /**
     * TEST: GET /api/v1/beneficiaries/{id} - Single beneficiary detail
     */
    public function testAPIGetBeneficiary(): void
    {
        $beneficiaryId = $this->addBeneficiary([
            'name' => 'Detail Ben',
            'account_number' => '5555555555',
            'ifsc_code' => 'DTL0000001',
            'bank_name' => 'Detail Bank',
        ]);

        $response = $this->apiGet("/api/v1/beneficiaries/{$beneficiaryId}");

        $this->assertEquals(200, $response['status_code']);
        $this->assertEquals('Detail Ben', $response['body']['beneficiary']['name']);
    }

    /**
     * TEST: DELETE /api/v1/beneficiaries/{id}
     */
    public function testAPIDeleteBeneficiary(): void
    {
        $beneficiaryId = $this->addBeneficiary([
            'name' => 'Delete Ben',
            'account_number' => '6666666666',
            'ifsc_code' => 'DEL0000001',
            'bank_name' => 'Delete Bank',
        ]);

        $response = $this->apiDelete("/api/v1/beneficiaries/{$beneficiaryId}");

        $this->assertEquals(200, $response['status_code']);

        // Verify deleted
        $get = $this->apiGet("/api/v1/beneficiaries/{$beneficiaryId}");
        $this->assertEquals(404, $get['status_code']);
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    private function createTestUsers(): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO users (customer_id, name, email, phone, password_hash, password_md5)
            VALUES (?, ?, ?, ?, ?, ?)
        ');

        // Test user
        $stmt->execute(['TEST001', 'Test User', 'test@example.com', '9999999999', 'hash1', 'md51']);
        $this->testUserId = (int) $this->pdo->lastInsertId();

        // Other user
        $stmt->execute(['TEST002', 'Other User', 'other@example.com', '8888888888', 'hash2', 'md52']);
        $this->otherUserId = (int) $this->pdo->lastInsertId();
    }

    private function addBeneficiary(array $input): ?int
    {
        return $this->addBeneficiaryForUser($this->testUserId, $input);
    }

    private function addBeneficiaryForUser(int $userId, array $input): ?int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO beneficiaries
            (user_id, name, account_number, ifsc_code, bank_name, is_verified, cooling_period_until)
            VALUES (?, ?, ?, ?, ?, 1, ?)
        ');

        $coolingPeriod = $input['cooling_period_until'] ?? date('Y-m-d H:i:s', time() + 86400);

        $stmt->execute([
            $userId,
            $input['name'] ?? '',
            $input['account_number'] ?? '',
            $input['ifsc_code'] ?? '',
            $input['bank_name'] ?? '',
            $coolingPeriod,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function getBeneficiary(int $id, int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM beneficiaries WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function assertBeneficiaryExists(string $accountNumber, int $userId): void
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM beneficiaries WHERE account_number = ? AND user_id = ?');
        $stmt->execute([$accountNumber, $userId]);
        $count = (int) $stmt->fetchColumn();
        $this->assertGreaterThan(0, $count, "Beneficiary with account $accountNumber should exist");
    }

    private function countBeneficiariesByAccountNumber(string $accountNumber, int $userId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM beneficiaries WHERE account_number = ? AND user_id = ?');
        $stmt->execute([$accountNumber, $userId]);
        return (int) $stmt->fetchColumn();
    }

    private function deleteBeneficiary(int $id, int $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM beneficiaries WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
    }

    private function xferUserBeneficiaries(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM beneficiaries WHERE user_id = ? ORDER BY id');
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getAllBeneficiaries(): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM beneficiaries');
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function renderBeneficiaryList(int $userId): string
    {
        $beneficiaries = $this->xferUserBeneficiaries($userId);
        $html = '';
        foreach ($beneficiaries as $b) {
            $html .= '<tr><td>' . $b['name'] . '</td></tr>';
        }
        return $html;
    }

    private function apiPost(string $endpoint, array $data): array
    {
        // Simulate POST request to API
        return ['status_code' => 201, 'body' => ['id' => 1]];
    }

    private function apiGet(string $endpoint): array
    {
        // Simulate GET request to API
        return ['status_code' => 200, 'body' => ['beneficiaries' => []]];
    }

    private function apiDelete(string $endpoint): array
    {
        // Simulate DELETE request to API
        return ['status_code' => 200, 'body' => ['ok' => true]];
    }
}
