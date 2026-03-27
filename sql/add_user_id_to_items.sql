USE loyola_lost_and_found;

-- Add user_id column to items table to track which user submitted each item
ALTER TABLE items ADD COLUMN user_id INT DEFAULT NULL;

-- Add foreign key constraint
ALTER TABLE items ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;
