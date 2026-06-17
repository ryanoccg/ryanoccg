-- Make the site owner an admin (no-op if not registered yet).
UPDATE users SET is_admin = 1 WHERE email = 'ryanoccg@gmail.com';
