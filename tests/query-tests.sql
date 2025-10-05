-- Sample query tests for performance verification
EXPLAIN SELECT * FROM bookings WHERE booking_date = CURDATE();
EXPLAIN SELECT * FROM bookings WHERE booking_id = 'SAMPLE';
EXPLAIN SELECT * FROM activity_logs WHERE log_type = 'booking' ORDER BY created_at DESC LIMIT 20;
