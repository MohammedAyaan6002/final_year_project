USE loyola_lost_and_found;

-- Add 'claimed' status to items table status enum
ALTER TABLE items MODIFY status ENUM('pending','approved','rejected','claimed') DEFAULT 'pending';
