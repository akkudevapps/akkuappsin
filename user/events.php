<?php
// user/events.php
define('AKKUAPPS_LOADED', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';

$user = getCurrentUser();
if (!$user) {
    header("Location: /auth/login.php");
    exit;
}

$message = '';
$error = '';

// Handle event creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_event'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $eventDate = $_POST['event_date'] ?? '';
    $location = trim($_POST['location'] ?? '');
    $isPublic = isset($_POST['is_public']) ? 1 : 0;
    
    if (empty($title) || strlen($title) < 3) {
        $error = "Event title must be at least 3 characters";
    } elseif (empty($eventDate)) {
        $error = "Event date is required";
    } else {
        try {
            global $pdo;
            $eventId = generateUUID();
            $stmt = $pdo->prepare("
                INSERT INTO events 
                (event_id, title, description, event_date, location, created_by, is_public, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$eventId, $title, $description, $eventDate, $location, $user['user_id'], $isPublic]);
            
            // Auto-RSVP creator
            $pdo->prepare("
                INSERT INTO event_rsvps 
                (event_id, user_id, rsvp_status, rsvp_at) 
                VALUES (?, ?, 'going', NOW())
            ")->execute([$eventId, $user['user_id']]);
            
            $message = "Event created successfully!";
        } catch (Exception $e) {
            $error = "Error creating event: " . $e->getMessage();
        }
    }
}

// Handle RSVP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rsvp'])) {
    $eventId = $_POST['event_id'];
    $rsvpStatus = $_POST['rsvp_status'];
    
    try {
        global $pdo;
        // Check if already RSVP'd
        $stmt = $pdo->prepare("SELECT id FROM event_rsvps WHERE event_id = ? AND user_id = ?");
        $stmt->execute([$eventId, $user['user_id']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $stmt = $pdo->prepare("
                UPDATE event_rsvps 
                SET rsvp_status = ?, rsvp_at = NOW() 
                WHERE event_id = ? AND user_id = ?
            ");
            $stmt->execute([$rsvpStatus, $eventId, $user['user_id']]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO event_rsvps 
                (event_id, user_id, rsvp_status, rsvp_at) 
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$eventId, $user['user_id'], $rsvpStatus]);
        }
        
        $message = "RSVP updated successfully!";
    } catch (Exception $e) {
        $error = "Error updating RSVP: " . $e->getMessage();
    }
}

// Get upcoming events
try {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT e.*, u.name as creator_name, u.avatar as creator_avatar,
               (SELECT COUNT(*) FROM event_rsvps WHERE event_id = e.event_id AND rsvp_status = 'going') as going_count,
               (SELECT COUNT(*) FROM event_rsvps WHERE event_id = e.event_id AND rsvp_status = 'maybe') as maybe_count,
               er.rsvp_status as user_rsvp
        FROM events e
        JOIN users u ON e.created_by = u.user_id
        LEFT JOIN event_rsvps er ON e.event_id = er.event_id AND er.user_id = ?
        WHERE e.event_date >= CURDATE()
        ORDER BY e.event_date ASC, e.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$user['user_id']]);
    $events = $stmt->fetchAll();
} catch (Exception $e) {
    $events = [];
    $error = "Error loading events: " . $e->getMessage();
}

// Get user's events
try {
    $stmt = $pdo->prepare("
        SELECT e.*, u.name as creator_name, u.avatar as creator_avatar,
               er.rsvp_status as user_rsvp,
               (SELECT COUNT(*) FROM event_rsvps WHERE event_id = e.event_id AND rsvp_status = 'going') as going_count
        FROM events e
        JOIN users u ON e.created_by = u.user_id
        JOIN event_rsvps er ON e.event_id = er.event_id
        WHERE er.user_id = ? AND e.event_date >= CURDATE()
        ORDER BY e.event_date ASC
    ");
    $stmt->execute([$user['user_id']]);
    $userEvents = $stmt->fetchAll();
} catch (Exception $e) {
    $userEvents = [];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events - AkkuApps</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css?v=<?= time() ?>">
    <style>
        .event-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .event-date {
            background: var(--accent-color);
            color: white;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            margin-right: 15px;
            min-width: 80px;
        }
        .event-date .day {
            font-size: 1.5rem;
            font-weight: bold;
            display: block;
        }
        .event-date .month {
            font-size: 0.9rem;
            text-transform: uppercase;
        }
        .rsvp-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        .rsvp-btn {
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .rsvp-btn.active {
            background: var(--accent-color);
            color: white;
        }
        .rsvp-btn:not(.active) {
            background: var(--secondary-bg);
            color: var(--text-primary);
        }
    </style>
</head>
<body>
    <?php include '../components/header.php'; ?>
    
    <div class="dashboard-container">
        <?php include '../components/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="welcome-banner">
                <h1>Events</h1>
                <p>Discover and join events in your community</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Create Event Form -->
            <div class="chart-container animate-slideUp">
                <h2>Create New Event</h2>
                <form method="POST" style="margin-top: 20px; background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px;">
                    <input type="hidden" name="create_event" value="1">
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 8px; color: var(--text-primary);">Event Title</label>
                        <input type="text" name="title" required maxlength="100"
                               style="width: 100%; padding: 12px; background: var(--secondary-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary);">
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div>
                            <label style="display: block; margin-bottom: 8px; color: var(--text-primary);">Event Date & Time</label>
                            <input type="datetime-local" name="event_date" required
                                   style="width: 100%; padding: 12px; background: var(--secondary-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary);">
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 8px; color: var(--text-primary);">Location</label>
                            <input type="text" name="location" maxlength="100"
                                   style="width: 100%; padding: 12px; background: var(--secondary-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary);"
                                   placeholder="Online or physical location">
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 8px; color: var(--text-primary);">Description</label>
                        <textarea name="description" rows="4" maxlength="1000"
                                  style="width: 100%; padding: 12px; background: var(--secondary-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary);"></textarea>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: flex; align-items: center; color: var(--text-primary);">
                            <input type="checkbox" name="is_public" value="1" checked style="margin-right: 10px;">
                            Public Event (visible to all users)
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-calendar-plus"></i> Create Event
                    </button>
                </form>
            </div>

            <!-- My Events -->
            <?php if (!empty($userEvents)): ?>
            <div class="chart-container animate-slideUp">
                <h2>My Events</h2>
                <div style="margin-top: 20px;">
                    <?php foreach ($userEvents as $event): ?>
                        <div class="event-card">
                            <div style="display: flex; align-items: flex-start;">
                                <div class="event-date">
                                    <span class="day"><?= date('d', strtotime($event['event_date'])) ?></span>
                                    <span class="month"><?= date('M', strtotime($event['event_date'])) ?></span>
                                </div>
                                <div style="flex: 1;">
                                    <h3 style="color: var(--text-primary); margin: 0 0 10px 0;">
                                        <?= htmlspecialchars($event['title']) ?>
                                        <?php if (!$event['is_public']): ?>
                                            <span style="background: #f59e0b; color: white; padding: 3px 8px; border-radius: 20px; font-size: 0.7em; margin-left: 10px;">
                                                Private
                                            </span>
                                        <?php endif; ?>
                                    </h3>
                                    <div style="color: var(--text-secondary); margin-bottom: 10px;">
                                        <i class="fas fa-calendar"></i> <?= date('M j, Y g:i A', strtotime($event['event_date'])) ?> •
                                        <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($event['location'] ?: 'Online') ?>
                                    </div>
                                    <div style="color: var(--text-primary); margin-bottom: 15px;">
                                        <?= htmlspecialchars($event['description']) ?>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                                        <div style="display: flex; align-items: center; gap: 5px;">
                                            <i class="fas fa-user-check" style="color: #10b981;"></i>
                                            <span><?= $event['going_count'] ?> going</span>
                                        </div>
                                        <?php if ($event['user_rsvp']): ?>
                                            <div style="display: flex; align-items: center; gap: 5px;">
                                                <i class="fas fa-ticket-alt" style="color: var(--accent-color);"></i>
                                                <span>You're <?= $event['user_rsvp'] ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Upcoming Events -->
            <div class="chart-container animate-slideUp">
                <h2>Upcoming Events</h2>
                <div style="margin-top: 20px;">
                    <?php if (empty($events)): ?>
                        <p style="color: var(--text-secondary); text-align: center; padding: 40px;">
                            No upcoming events. Be the first to create one!
                        </p>
                    <?php else: ?>
                        <?php foreach ($events as $event): ?>
                            <div class="event-card">
                                <div style="display: flex; align-items: flex-start;">
                                    <div class="event-date">
                                        <span class="day"><?= date('d', strtotime($event['event_date'])) ?></span>
                                        <span class="month"><?= date('M', strtotime($event['event_date'])) ?></span>
                                    </div>
                                    <div style="flex: 1;">
                                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                            <div>
                                                <h3 style="color: var(--text-primary); margin: 0 0 10px 0;">
                                                    <?= htmlspecialchars($event['title']) ?>
                                                    <?php if (!$event['is_public']): ?>
                                                        <span style="background: #f59e0b; color: white; padding: 3px 8px; border-radius: 20px; font-size: 0.7em; margin-left: 10px;">
                                                            Private
                                                        </span>
                                                    <?php endif; ?>
                                                </h3>
                                                <div style="color: var(--text-secondary); margin-bottom: 10px;">
                                                    <i class="fas fa-user"></i> <?= htmlspecialchars($event['creator_name']) ?> •
                                                    <i class="fas fa-calendar"></i> <?= date('M j, Y g:i A', strtotime($event['event_date'])) ?> •
                                                    <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($event['location'] ?: 'Online') ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($event['description'])): ?>
                                            <div style="color: var(--text-primary); margin-bottom: 15px;">
                                                <?= htmlspecialchars($event['description']) ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px; flex-wrap: wrap;">
                                            <div style="display: flex; align-items: center; gap: 5px;">
                                                <i class="fas fa-user-check" style="color: #10b981;"></i>
                                                <span><?= $event['going_count'] ?> going</span>
                                            </div>
                                            <?php if ($event['maybe_count'] > 0): ?>
                                                <div style="display: flex; align-items: center; gap: 5px;">
                                                    <i class="fas fa-question-circle" style="color: #f59e0b;"></i>
                                                    <span><?= $event['maybe_count'] ?> maybe</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <form method="POST" class="rsvp-buttons">
                                            <input type="hidden" name="event_id" value="<?= $event['event_id'] ?>">
                                            <input type="hidden" name="rsvp" value="1">
                                            <button type="submit" name="rsvp_status" value="going" 
                                                    class="rsvp-btn <?= $event['user_rsvp'] === 'going' ? 'active' : '' ?>">
                                                <i class="fas fa-check"></i> Going
                                            </button>
                                            <button type="submit" name="rsvp_status" value="maybe" 
                                                    class="rsvp-btn <?= $event['user_rsvp'] === 'maybe' ? 'active' : '' ?>">
                                                <i class="fas fa-question"></i> Maybe
                                            </button>
                                            <button type="submit" name="rsvp_status" value="not_going" 
                                                    class="rsvp-btn <?= $event['user_rsvp'] === 'not_going' ? 'active' : '' ?>">
                                                <i class="fas fa-times"></i> Not Going
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/theme-switcher.js?v=<?= time() ?>"></script>
</body>
</html>
