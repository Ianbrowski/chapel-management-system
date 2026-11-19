<?php
class SacramentalCertificateService
{
    private mysqli $conn;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    public function logCertificateIssuance(int $recordId, string $issuedTo, string $purpose, int $issuedBy, callable $auditLogger): int|false
    {
        $certNo = $this->generateCertNo($recordId);

        $stmt = $this->conn->prepare(
            "INSERT INTO certificate_issuances (record_id, issued_to, purpose, issued_by, certificate_no)
             VALUES (?,?,?,?,?)"
        );
        $stmt->bind_param('issis', $recordId, $issuedTo, $purpose, $issuedBy, $certNo);
        $ok = $stmt->execute();
        $issuanceId = $ok ? (int) $this->conn->insert_id : false;
        $stmt->close();

        if ($ok) {
            $auditLogger($recordId, 'printed', $issuedBy, null, ['issued_to' => $issuedTo, 'cert_no' => $certNo]);
        }

        return $issuanceId;
    }

    public function getCertificateHistory(int $recordId): array
    {
        $sql = "SELECT ci.*, u.user AS issued_by_name
                FROM certificate_issuances ci
                LEFT JOIN users u ON u.id = ci.issued_by
                WHERE ci.record_id = ?
                ORDER BY ci.issued_at DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $recordId);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
        $stmt->close();
        return $rows;
    }

    private function generateCertNo(int $recordId): string
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*)+1 AS nxt FROM certificate_issuances WHERE record_id=?");
        $stmt->bind_param('i', $recordId);
        $stmt->execute();
        $next = $stmt->get_result()->fetch_assoc()['nxt'] ?? 1;
        $stmt->close();
        return 'CERT-' . $recordId . '-' . sprintf('%03d', $next);
    }
}
