<?php

class support
{
    /**
     * Create a new ticket. Caller is responsible for validating/sanitizing
     * $data first — this method only handles the DB write.
     *
     * @param PDO   $pdo
     * @param array $data Expected keys: email, first_name, last_name, order_id,
     *                    delivery_date, fish_sku, doa_count, resolution,
     *                    resolution_other, description
     * @return array{ticket_id:int, ticket_ref:string}
     */
    public static function createTicket(PDO $pdo, array $data): array
    {
        $ticketRef = self::generateTicketRef($pdo);

        $stmt = $pdo->prepare("
            INSERT INTO tickets
                (ticket_ref, email, first_name, last_name, order_id, delivery_date,
                 fish_sku, doa_count, resolution, resolution_other, description, status)
            VALUES
                (:ticket_ref, :email, :first_name, :last_name, :order_id, :delivery_date,
                 :fish_sku, :doa_count, :resolution, :resolution_other, :description, 'pending')
        ");

        $stmt->execute([
            'ticket_ref'       => $ticketRef,
            'email'            => $data['email'],
            'first_name'       => $data['first_name'],
            'last_name'        => $data['last_name'],
            'order_id'         => $data['order_id'],
            'delivery_date'    => $data['delivery_date'],
            'fish_sku'         => $data['fish_sku'],
            'doa_count'        => $data['doa_count'],
            'resolution'       => $data['resolution'],
            'resolution_other' => $data['resolution_other'],
            'description'      => $data['description'],
        ]);

        return [
            'ticket_id'  => (int) $pdo->lastInsertId(),
            'ticket_ref' => $ticketRef,
        ];
    }

    public static function saveTicketFile(
        PDO $pdo,
        int $ticketId,
        string $originalFilename,
        string $storedFilename,
        string $mimeType,
        int $sizeBytes
    ): int {
        $stmt = $pdo->prepare("
            INSERT INTO ticket_files
                (ticket_id, original_filename, stored_filename, mime_type, size_bytes)
            VALUES
                (:ticket_id, :original_filename, :stored_filename, :mime_type, :size_bytes)
        ");

        $stmt->execute([
            'ticket_id'         => $ticketId,
            'original_filename' => $originalFilename,
            'stored_filename'   => $storedFilename,
            'mime_type'         => $mimeType,
            'size_bytes'        => $sizeBytes,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function getTickets(PDO $pdo, int $limit = 200): array
    {
        $stmt = $pdo->prepare("
            SELECT * FROM tickets
            ORDER BY created_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function getTicketById(PDO $pdo, int $ticketId): ?array
    {
        $stmt = $pdo->prepare("SELECT * FROM tickets WHERE ticket_id = :ticket_id");
        $stmt->execute(['ticket_id' => $ticketId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function getTicketsByEmail(PDO $pdo, string $email): array
    {
        $stmt = $pdo->prepare("
            SELECT * FROM tickets
            WHERE email = :email
            ORDER BY created_at DESC
        ");
        $stmt->execute(['email' => $email]);

        return $stmt->fetchAll();
    }

    /**
     * Files attached to a ticket.
     */
    public static function getTicketFiles(PDO $pdo, int $ticketId): array
    {
        $stmt = $pdo->prepare("
            SELECT * FROM ticket_files
            WHERE ticket_id = :ticket_id
            ORDER BY created_at ASC
        ");
        $stmt->execute(['ticket_id' => $ticketId]);

        return $stmt->fetchAll();
    }

    public static function updateTicketStatus(
        PDO $pdo,
        int $ticketId,
        string $status,
        ?string $response = null
    ): bool {
        if (!in_array($status, ['pending', 'approved', 'denied'], true)) {
            throw new InvalidArgumentException("Invalid ticket status: {$status}");
        }

        $stmt = $pdo->prepare("
            UPDATE tickets
            SET status = :status, admin_response = :admin_response
            WHERE ticket_id = :ticket_id
        ");

        return $stmt->execute([
            'status'         => $status,
            'admin_response' => $response,
            'ticket_id'      => $ticketId,
        ]);
    }

    
    //   Generates a short, human-facing reference like "DOA-4F91C2", checked
    //   against the DB for uniqueness (extremely unlikely to collide, but the
    //   client's original UI already implies a small, memorable code — this
    //   keeps that behavior but makes it authoritative server-side instead of
    //   client-generated via Date.now()).
     
    private static function generateTicketRef(PDO $pdo): string
    {
        do {
            $ref = 'DOA-' . strtoupper(bin2hex(random_bytes(3)));

            $stmt = $pdo->prepare("SELECT 1 FROM tickets WHERE ticket_ref = :ref");
            $stmt->execute(['ref' => $ref]);
            $exists = (bool) $stmt->fetchColumn();
        } while ($exists);

        return $ref;
    }
}
