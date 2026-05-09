<?php
/**
 * Booking Class
 * Handles seat booking, cancellation, and booking management
 */

require_once 'Database.php';

class Booking {
    private $db;
    private $conn;
    
    // Booking properties
    private $id;
    private $bookingId;
    private $userId;
    private $busNumber;
    private $route;
    private $date;
    private $time;
    private $pickupPoint;
    private $seatNumber;
    private $status;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = Database::getInstance();
        $this->conn = $this->db->getConnection();
    }
    
    /**
     * Create a new booking
     * @param array $data Booking data
     * @return array Response with success status
     */
    public function create($data) {
        try {
            // Validate required fields
            if (empty($data['userId']) || empty($data['bus']) || empty($data['route']) || 
                empty($data['date']) || empty($data['time']) || empty($data['pickup']) || 
                empty($data['seat'])) {
                return [
                    'success' => false,
                    'message' => 'All fields are required'
                ];
            }
            
            // Sanitize input
            $userId = (int)$data['userId'];
            $busNumber = $this->db->sanitize($data['bus']);
            $route = $this->db->sanitize($data['route']);
            $date = $this->db->sanitize($data['date']);
            $time = $this->db->sanitize($data['time']);
            $pickupPoint = $this->db->sanitize($data['pickup']);
            $seatNumber = $this->db->sanitize($data['seat']);
            
            // Generate booking ID
            $bookingId = $this->db->generateId('BC');
            
            // Check if seat is already booked
            if ($this->isSeatBooked($busNumber, $date, $time, $seatNumber)) {
                return [
                    'success' => false,
                    'message' => 'This seat is already booked'
                ];
            }
            
            // Check if user already has a booking for this bus/time
            if ($this->hasUserBooked($userId, $busNumber, $date, $time)) {
                return [
                    'success' => false,
                    'message' => 'You already have a booking for this bus'
                ];
            }
            
            // Insert booking
            $stmt = $this->conn->prepare(
                "INSERT INTO bookings (booking_id, user_id, bus_number, route, date, time, 
                pickup_point, seat_number, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', NOW())"
            );
            
            $stmt->bind_param("sissssss", $bookingId, $userId, $busNumber, $route, 
                              $date, $time, $pickupPoint, $seatNumber);
            
            if ($stmt->execute()) {
                $id = $this->conn->insert_id;
                $stmt->close();
                
                return [
                    'success' => true,
                    'message' => 'Booking confirmed successfully',
                    'booking' => [
                        'id' => $bookingId,
                        'bus' => $busNumber,
                        'route' => $route,
                        'date' => $date,
                        'time' => $time,
                        'seat' => $seatNumber,
                        'pickup' => $pickupPoint,
                        'status' => 'confirmed'
                    ]
                ];
            } else {
                $stmt->close();
                throw new Exception($this->conn->error);
            }
            
        } catch (Exception $e) {
            error_log("Booking creation error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Booking failed. Please try again.'
            ];
        }
    }
    
    /**
     * Cancel a booking
     * @param string $bookingId Booking ID
     * @param int $userId User ID (for verification)
     * @return array Response with success status
     */
    public function cancel($bookingId, $userId = null) {
        try {
            $bookingId = $this->db->sanitize($bookingId);
            
            // Build query with optional user verification
            if ($userId) {
                $stmt = $this->conn->prepare(
                    "UPDATE bookings SET status = 'cancelled', updated_at = NOW() 
                     WHERE booking_id = ? AND user_id = ?"
                );
                $stmt->bind_param("si", $bookingId, $userId);
            } else {
                $stmt = $this->conn->prepare(
                    "UPDATE bookings SET status = 'cancelled', updated_at = NOW() 
                     WHERE booking_id = ?"
                );
                $stmt->bind_param("s", $bookingId);
            }
            
            if ($stmt->execute()) {
                $affected = $this->conn->affected_rows;
                $stmt->close();
                
                if ($affected > 0) {
                    return [
                        'success' => true,
                        'message' => 'Booking cancelled successfully'
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => 'Booking not found or already cancelled'
                    ];
                }
            } else {
                $stmt->close();
                throw new Exception($this->conn->error);
            }
            
        } catch (Exception $e) {
            error_log("Booking cancellation error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to cancel booking'
            ];
        }
    }
    
    /**
     * Get user's bookings
     * @param int $userId User ID
     * @param string $status Filter by status (optional)
     * @return array List of bookings
     */
    public function getUserBookings($userId, $status = null) {
        $userId = $this->db->sanitize($userId);
        
        $sql = "SELECT * FROM bookings WHERE user_id = '$userId'";
        
        if ($status) {
            $status = $this->db->sanitize($status);
            $sql .= " AND status = '$status'";
        }
        
        $sql .= " ORDER BY date DESC, time DESC";
        
        $result = $this->conn->query($sql);
        $bookings = [];
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $bookings[] = $row;
            }
        }
        
        return $bookings;
    }
    
    /**
     * Get all bookings (admin)
     * @param array $filters Optional filters
     * @return array List of bookings
     */
    public function getAll($filters = []) {
        $sql = "SELECT b.*, u.name as user_name, u.username, u.email, u.phone 
                FROM bookings b 
                LEFT JOIN users u ON b.user_id = u.id 
                WHERE 1=1";
        
        // Apply filters
        if (isset($filters['status'])) {
            $status = $this->db->sanitize($filters['status']);
            $sql .= " AND b.status = '$status'";
        }
        
        if (isset($filters['date'])) {
            $date = $this->db->sanitize($filters['date']);
            $sql .= " AND b.date = '$date'";
        }
        
        if (isset($filters['busNumber'])) {
            $busNumber = $this->db->sanitize($filters['busNumber']);
            $sql .= " AND b.bus_number = '$busNumber'";
        }
        
        if (isset($filters['route'])) {
            $route = $this->db->sanitize($filters['route']);
            $sql .= " AND b.route = '$route'";
        }
        
        $sql .= " ORDER BY b.date DESC, b.time DESC";
        
        $result = $this->conn->query($sql);
        $bookings = [];
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $bookings[] = $row;
            }
        }
        
        return $bookings;
    }
    
    /**
     * Get booking by ID
     * @param string $bookingId Booking ID
     * @return array|null Booking data or null if not found
     */
    public function getById($bookingId) {
        $bookingId = $this->db->sanitize($bookingId);
        
        $stmt = $this->conn->prepare(
            "SELECT b.*, u.name as user_name, u.username, u.email, u.phone 
             FROM bookings b 
             LEFT JOIN users u ON b.user_id = u.id 
             WHERE b.booking_id = ?"
        );
        
        $stmt->bind_param("s", $bookingId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $booking = $result->fetch_assoc();
            $stmt->close();
            return $booking;
        }
        
        $stmt->close();
        return null;
    }
    
    /**
     * Check if a seat is already booked
     * @param string $busNumber Bus number
     * @param string $date Date
     * @param string $time Time
     * @param string $seatNumber Seat number
     * @return bool True if booked, false otherwise
     */
    private function isSeatBooked($busNumber, $date, $time, $seatNumber) {
        $stmt = $this->conn->prepare(
            "SELECT id FROM bookings 
             WHERE bus_number = ? AND date = ? AND time = ? AND seat_number = ? 
             AND status = 'confirmed'"
        );
        
        $stmt->bind_param("ssss", $busNumber, $date, $time, $seatNumber);
        $stmt->execute();
        $result = $stmt->get_result();
        $isBooked = $result->num_rows > 0;
        $stmt->close();
        
        return $isBooked;
    }
    
    /**
     * Check if user already has a booking
     * @param int $userId User ID
     * @param string $busNumber Bus number
     * @param string $date Date
     * @param string $time Time
     * @return bool True if user has booked, false otherwise
     */
    private function hasUserBooked($userId, $busNumber, $date, $time) {
        $stmt = $this->conn->prepare(
            "SELECT id FROM bookings 
             WHERE user_id = ? AND bus_number = ? AND date = ? AND time = ? 
             AND status = 'confirmed'"
        );
        
        $stmt->bind_param("isss", $userId, $busNumber, $date, $time);
        $stmt->execute();
        $result = $stmt->get_result();
        $hasBooked = $result->num_rows > 0;
        $stmt->close();
        
        return $hasBooked;
    }
    
    /**
     * Get booked seats for a bus
     * @param string $busNumber Bus number
     * @param string $date Date
     * @param string $time Time
     * @return array List of booked seat numbers
     */
    public function getBookedSeats($busNumber, $date, $time) {
        $busNumber = $this->db->sanitize($busNumber);
        $date = $this->db->sanitize($date);
        $time = $this->db->sanitize($time);
        
        $sql = "SELECT seat_number FROM bookings 
                WHERE bus_number = '$busNumber' AND date = '$date' AND time = '$time' 
                AND status = 'confirmed'";
        
        $result = $this->conn->query($sql);
        $seats = [];
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $seats[] = $row['seat_number'];
            }
        }
        
        return $seats;
    }
    
    /**
     * Get booking statistics
     * @return array Booking statistics
     */
    public function getStatistics() {
        $stats = [];
        
        // Total bookings
        $result = $this->conn->query("SELECT COUNT(*) as count FROM bookings");
        $stats['total'] = $result->fetch_assoc()['count'];
        
        // Confirmed bookings
        $result = $this->conn->query("SELECT COUNT(*) as count FROM bookings WHERE status = 'confirmed'");
        $stats['confirmed'] = $result->fetch_assoc()['count'];
        
        // Today's bookings
        $result = $this->conn->query("SELECT COUNT(*) as count FROM bookings WHERE date = CURDATE()");
        $stats['today'] = $result->fetch_assoc()['count'];
        
        // This week's bookings
        $result = $this->conn->query("SELECT COUNT(*) as count FROM bookings WHERE WEEK(date) = WEEK(NOW())");
        $stats['this_week'] = $result->fetch_assoc()['count'];
        
        // Cancelled bookings
        $result = $this->conn->query("SELECT COUNT(*) as count FROM bookings WHERE status = 'cancelled'");
        $stats['cancelled'] = $result->fetch_assoc()['count'];
        
        return $stats;
    }
}
?>


    public function getByBookingId($bookingId) {
        $bookingId = $this->db->sanitize($bookingId);
        $stmt = $this->conn->prepare(
            "SELECT * FROM bookings WHERE booking_id = ? AND status != 'cancelled'"
        );
        $stmt->bind_param("s", $bookingId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $booking = $result->fetch_assoc();
            $stmt->close();
            return $booking;
        }
        $stmt->close();
        return null;
    }
