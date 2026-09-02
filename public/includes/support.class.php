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
     * Verify and retrieve ticket by matching both ticket_ref and customer email.
     * Both must match the same database record before access is granted.
     *
     * @param PDO    $pdo
     * @param string $ticketRef
     * @param string $email
     * @return array|null Ticket row if verified, null otherwise.
     */
    public static function getTicketByRefAndEmail(PDO $pdo, string $ticketRef, string $email): ?array
    {
        $stmt = $pdo->prepare("
            SELECT * FROM tickets
            WHERE ticket_ref = :ticket_ref AND LOWER(email) = LOWER(:email)
            LIMIT 1
        ");
        $stmt->execute([
            'ticket_ref' => trim($ticketRef),
            'email'      => trim($email),
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Retrieve ticket by ticket_ref only.
     *
     * @param PDO    $pdo
     * @param string $ticketRef
     * @return array|null Ticket row if found, null otherwise.
     */
    public static function getTicketByRef(PDO $pdo, string $ticketRef): ?array
    {
        $stmt = $pdo->prepare("
            SELECT * FROM tickets
            WHERE ticket_ref = :ticket_ref
            LIMIT 1
        ");
        $stmt->execute(['ticket_ref' => trim($ticketRef)]);
        $row = $stmt->fetch();

        return $row ?: null;
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

    /**
     * Get related order information from the orders table.
     */
    public static function getOrderDetails(PDO $pdo, string $orderId): ?array
    {
        $stmt = $pdo->prepare("
            SELECT order_id, first_name, last_name, email, order_date, country, total_value, sku, created_at, modified_at
            FROM orders
            WHERE order_id = :order_id
        ");
        $stmt->execute(['order_id' => $orderId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Approve a pending ticket. Returns true if status changed, false otherwise.
     */
    public static function approveTicket(PDO $pdo, int $ticketId): bool
    {
        $stmt = $pdo->prepare("
            UPDATE tickets
            SET status = 'approved'
            WHERE ticket_id = :ticket_id AND status = 'pending'
        ");
        $stmt->execute(['ticket_id' => $ticketId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Deny a pending ticket with an admin response/reason.
     */
    public static function denyTicket(PDO $pdo, int $ticketId, string $adminResponse): bool
    {
        $trimmedResponse = trim($adminResponse);
        if ($trimmedResponse === '') {
            throw new InvalidArgumentException("Admin response is required when denying a request.");
        }

        $stmt = $pdo->prepare("
            UPDATE tickets
            SET status = 'denied', admin_response = :admin_response
            WHERE ticket_id = :ticket_id AND status = 'pending'
        ");
        $stmt->execute([
            'ticket_id'      => $ticketId,
            'admin_response' => $trimmedResponse,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Update/save admin response for a ticket.
     */
    public static function saveAdminResponse(PDO $pdo, int $ticketId, string $response): bool
    {
        $stmt = $pdo->prepare("
            UPDATE tickets
            SET admin_response = :admin_response
            WHERE ticket_id = :ticket_id
        ");
        return $stmt->execute([
            'ticket_id'      => $ticketId,
            'admin_response' => $response,
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
