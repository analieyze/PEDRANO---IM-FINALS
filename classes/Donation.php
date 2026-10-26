<?php
declare(strict_types=1);

/**
 * Donation Management Class
 * Relief Logistics & Inventory System
 * Includes ACID Transactional methods for atomic inventory operations.
 */
class Donation {
    private PDO $conn;

    public function __construct(PDO $conn) {
        $this->conn = $conn;
    }

    public function getDonations(): array {
        $query = "SELECT donations.*, donors.contact_person AS donor_name, categories.category_name, categories.icon, categories.color
                  FROM donations 
                  LEFT JOIN donors ON donations.donor_id = donors.donor_id
                  LEFT JOIN categories ON donations.category_id = categories.category_id
                  ORDER BY donation_id DESC";
        $stmt = $this->conn->query($query);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function addDonation(string $item_name, int $quantity, string $donation_date, int $donor_id, int $category_id, string $status = 'Received'): bool {
        $query = "INSERT INTO donations (item_name, quantity, donation_date, donor_id, category_id, status) 
                  VALUES (:item_name, :quantity, :donation_date, :donor_id, :category_id, :status)";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            ':item_name' => $item_name,
            ':quantity' => $quantity,
            ':donation_date' => $donation_date,
            ':donor_id' => $donor_id,
            ':category_id' => $category_id,
            ':status' => $status
        ]);
    }

    /**
     * TRANSACTION 1 (OOP Method): Atomic Donation Intake with Audit Trail & Notifications
     */
    public function addDonationWithTransaction(string $item_name, int $quantity, string $donation_date, int $donor_id, int $category_id, int $user_id, string $status = 'Received'): int {
        $this->conn->beginTransaction();
        try {
            $stmt = $this->conn->prepare(
                "INSERT INTO donations (item_name, quantity, donation_date, donor_id, category_id, status) 
                 VALUES (:item_name, :quantity, :donation_date, :donor_id, :category_id, :status)"
            );
            $stmt->execute([
                ':item_name' => $item_name,
                ':quantity' => $quantity,
                ':donation_date' => $donation_date,
                ':donor_id' => $donor_id,
                ':category_id' => $category_id,
                ':status' => $status
            ]);
            $donationId = (int)$this->conn->lastInsertId();

            // Log activity atomically
            $logStmt = $this->conn->prepare(
                "INSERT INTO activitylogs (action, status, timestamp, user_id, donation_id)
                 VALUES ('CREATED_DONATION', 1, NOW(), :user_id, :donation_id)"
            );
            $logStmt->execute([':user_id' => $user_id, ':donation_id' => $donationId]);

            // Create notification atomically
            $noteStmt = $this->conn->prepare(
                "INSERT INTO notifications (message, event_date, is_read, user_id, donation_id)
                 VALUES (:message, :event_date, 0, :user_id, :donation_id)"
            );
            $noteStmt->execute([
                ':message' => "New relief supply recorded: {$item_name} ({$quantity} units)",
                ':event_date' => $donation_date,
                ':user_id' => $user_id,
                ':donation_id' => $donationId
            ]);

            $this->conn->commit();
            return $donationId;
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    public function updateDonation(int $donation_id, string $item_name, int $quantity, string $donation_date, int $donor_id, int $category_id, string $status = 'Received'): bool {
        $query = "UPDATE donations 
                  SET item_name = :item_name,
                      quantity = :quantity,
                      donation_date = :donation_date,
                      donor_id = :donor_id,
                      category_id = :category_id,
                      status = :status
                  WHERE donation_id = :donation_id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            ':donation_id' => $donation_id,
            ':item_name' => $item_name,
            ':quantity' => $quantity,
            ':donation_date' => $donation_date,
            ':donor_id' => $donor_id,
            ':category_id' => $category_id,
            ':status' => $status
        ]);
    }

    /**
     * TRANSACTION 2 (OOP Method): Atomic Status Transition & Field Dispatch Notification
     */
    public function updateStatusWithTransaction(int $donation_id, string $status, int $user_id): bool {
        $this->conn->beginTransaction();
        try {
            $checkStmt = $this->conn->prepare("SELECT item_name, quantity FROM donations WHERE donation_id = :id");
            $checkStmt->execute([':id' => $donation_id]);
            $item = $checkStmt->fetch(PDO::FETCH_ASSOC);
            if (!$item) {
                throw new Exception("Donation record not found.");
            }

            $stmt = $this->conn->prepare("UPDATE donations SET status = :status WHERE donation_id = :id");
            $stmt->execute([':status' => $status, ':id' => $donation_id]);

            $logStmt = $this->conn->prepare(
                "INSERT INTO activitylogs (action, status, timestamp, user_id, donation_id)
                 VALUES (:action, 1, NOW(), :user_id, :donation_id)"
            );
            $logStmt->execute([
                ':action' => "STATUS_CHANGE_TO_{$status}",
                ':user_id' => $user_id,
                ':donation_id' => $donation_id
            ]);

            if ($status === 'Released') {
                $noteStmt = $this->conn->prepare(
                    "INSERT INTO notifications (message, event_date, is_read, user_id, donation_id)
                     VALUES (:message, CURDATE(), 0, :user_id, :donation_id)"
                );
                $noteStmt->execute([
                    ':message' => "Relief batch dispatched to field: {$item['item_name']} ({$item['quantity']} units)",
                    ':user_id' => $user_id,
                    ':donation_id' => $donation_id
                ]);
            }

            $this->conn->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    public function deleteDonation(int $donation_id): bool {
        $query = "DELETE FROM donations WHERE donation_id = :donation_id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([':donation_id' => $donation_id]);
    }

    /**
     * TRANSACTION 3 (OOP Method): Atomic Deletion with Foreign Key Safety & Audit Trail
     */
    public function deleteDonationWithTransaction(int $donation_id, int $user_id): bool {
        $this->conn->beginTransaction();
        try {
            // Nullify foreign keys in activitylogs
            $this->conn->prepare("UPDATE activitylogs SET donation_id = NULL WHERE donation_id = ?")->execute([$donation_id]);

            // Remove notifications
            $this->conn->prepare("DELETE FROM notifications WHERE donation_id = ?")->execute([$donation_id]);

            // Delete donation
            $stmt = $this->conn->prepare("DELETE FROM donations WHERE donation_id = ?");
            $stmt->execute([$donation_id]);

            // Log activity
            $logStmt = $this->conn->prepare(
                "INSERT INTO activitylogs (action, status, timestamp, user_id, donation_id)
                 VALUES ('DELETED_DONATION', 1, NOW(), ?, NULL)"
            );
            $logStmt->execute([$user_id]);

            $this->conn->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    public function searchDonations(string $keyword): array {
        $query = "SELECT donations.*, donors.contact_person AS donor_name, categories.category_name, categories.icon, categories.color
                  FROM donations 
                  LEFT JOIN donors ON donations.donor_id = donors.donor_id
                  LEFT JOIN categories ON donations.category_id = categories.category_id
                  WHERE item_name LIKE :keyword OR donors.contact_person LIKE :keyword OR categories.category_name LIKE :keyword
                  ORDER BY donation_id DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':keyword' => '%' . $keyword . '%']);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
}
