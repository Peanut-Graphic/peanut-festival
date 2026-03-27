# Festival ML Integration — Quick Start

## 5-Minute Setup

### 1. Configure Environment Variables

Add to your `.env` file (or set in docker-compose):

```bash
FESTIVAL_DB_HOST=localhost
FESTIVAL_DB_PORT=3306
FESTIVAL_DB_NAME=wordpress_db
FESTIVAL_DB_USER=wp_user
FESTIVAL_DB_PASS=wp_password
FESTIVAL_DB_PREFIX=wp_
ML_SERVICE_API_KEY=optional_secret_key
MODEL_DIR=./models
```

### 2. Configure WordPress Plugin

In WordPress admin, add to `peanut_festival_settings` option (via WP-CLI or database):

```bash
# Via WP-CLI:
wp option update peanut_festival_settings '{"ml_enabled":true,"ml_api_key":"optional_secret_key","ml_auto_train":false}'
```

Or directly in PHP:

```php
update_option('peanut_festival_settings', [
    'ml_enabled' => true,
    'ml_api_key' => 'optional_secret_key',
    'ml_auto_train' => false,  // Set true for automatic weekly training
]);
```

### 3. Start ML Service

```bash
cd /sessions/hopeful-admiring-mayer/mnt/Peanut/ML-SERVICE
uvicorn app:app --host 127.0.0.1 --port 8100
```

### 4. Verify Service is Running

```bash
curl http://127.0.0.1:8100/health
# Response: {"status":"healthy","service":"peanut-ml","version":"1.0.0"}
```

## Usage Examples

### Example 1: Predict Attendance for a Show

```php
<?php
// Get the ML predictor
$predictor = Peanut_Festival_ML_Predictor::get_instance();

// Check service is available
if (!$predictor->is_available()) {
    echo "ML service is down!";
    exit;
}

// Predict attendance
$prediction = $predictor->predict_attendance([
    'festival_id' => 1,
    'venue_capacity' => 500,
    'day_of_week' => 5,      // Saturday
    'time_of_day' => 19,      // 7 PM
    'performer_count' => 2,
    'ticket_price' => 25.0,
    'weather_forecast' => 'sunny',
    'historical_shows' => 10,
]);

if (isset($prediction['error'])) {
    echo "Error: " . $prediction['error'];
} else {
    echo "Predicted attendance: " . $prediction['predicted_attendance'] . " people\n";
    echo "Capacity utilization: " . $prediction['capacity_utilization_pct'] . "%\n";
    echo "Confidence: " . $prediction['confidence_interval'][0] . " - " . $prediction['confidence_interval'][1] . "\n";
    echo "Key factors:\n";
    foreach ($prediction['key_factors'] as $factor) {
        echo "  - $factor\n";
    }
}
?>
```

**Expected Output:**
```
Predicted attendance: 385 people
Capacity utilization: 77.0%
Confidence: 335 - 435
Key factors:
  - Peak evening time boosts attendance
  - Weekend slot attracts larger crowds
```

### Example 2: Optimize Festival Schedule

```php
<?php
$predictor = Peanut_Festival_ML_Predictor::get_instance();

// Define your shows
$shows = [
    [
        'performer_id' => 10,
        'duration_minutes' => 45,
        'popularity_score' => 85.0,
    ],
    [
        'performer_id' => 11,
        'duration_minutes' => 60,
        'popularity_score' => 72.0,
    ],
    [
        'performer_id' => 12,
        'duration_minutes' => 50,
        'popularity_score' => 68.0,
    ],
];

// Get recommendations
$schedule = $predictor->optimize_schedule(1, $shows);

if (isset($schedule['error'])) {
    echo "Error: " . $schedule['error'];
} else {
    echo "Optimized Schedule:\n";
    foreach ($schedule['schedule'] as $slot) {
        echo "Performer {$slot['performer_id']}: ";
        echo "{$slot['start_time']} - {$slot['end_time']} ";
        echo "at Venue {$slot['venue_id']}\n";
        echo "  Expected: {$slot['predicted_attendance']} attendees\n";
    }
    echo "\nTotal predicted attendance: {$schedule['total_predicted_attendance']}\n";
    echo "Overall venue utilization: {$schedule['overall_venue_utilization_pct']}%\n";
}
?>
```

### Example 3: Recommend Vendor Placement

```php
<?php
$predictor = Peanut_Festival_ML_Predictor::get_instance();

// Get placement recommendations
$placement = $predictor->recommend_vendor_placement(1, [
    'vendor_count' => 12,
    'venue_layout_zones' => ['entrance', 'main_stage', 'food_court', 'exit'],
]);

if (isset($placement['error'])) {
    echo "Error: " . $placement['error'];
} else {
    echo "Vendor Placement Recommendations:\n\n";
    foreach ($placement['placements'] as $zone) {
        echo "Zone: {$zone['zone_name']}\n";
        echo "  Vendors: " . implode(', ', $zone['vendor_ids']) . "\n";
        echo "  Foot traffic: {$zone['predicted_foot_traffic']}\n";
        echo "  Revenue potential: \${$zone['transaction_potential']}\n\n";
    }
    echo "Total foot traffic: {$placement['total_foot_traffic_estimate']}\n";
    echo "Total revenue potential: \${$placement['revenue_potential']}\n";
}
?>
```

### Example 4: Manually Train Models

```php
<?php
$predictor = Peanut_Festival_ML_Predictor::get_instance();

echo "Training models...\n";
$result = $predictor->train_model();

if (isset($result['error'])) {
    echo "Training failed: {$result['error']}\n";
} else {
    echo "Training completed!\n";
    echo "  Status: {$result['status']}\n";
    echo "  Shows processed: {$result['attendance_samples']}\n";
    echo "  Transactions: {$result['vendor_samples']}\n";
    echo "  Model version: {$result['model_version']}\n";
}
?>
```

### Example 5: Check Model Statistics

```php
<?php
$predictor = Peanut_Festival_ML_Predictor::get_instance();

$stats = $predictor->get_model_stats();

if (isset($stats['error'])) {
    echo "Error: {$stats['error']}";
} else {
    echo "Model Statistics:\n";
    echo "  Model version: {$stats['model_version']}\n";
    echo "  Total festivals: {$stats['total_festivals']}\n";
    echo "  Total shows: {$stats['total_shows']}\n";
    echo "  Average attendance: {$stats['avg_attendance_per_show']}\n";
    echo "  Venue utilization: {$stats['avg_venue_utilization']}%\n";
    echo "  Total attendees: {$stats['total_attendees']}\n";
    echo "  Total check-ins: {$stats['total_check_ins']}\n";
}
?>
```

## Troubleshooting

### ML Service Won't Start

```bash
# Check Python installation
python3 --version

# Check dependencies
pip3 install fastapi uvicorn scikit-learn numpy pandas joblib pymysql pydantic

# Check port is available
lsof -i :8100

# Start with verbose output
uvicorn app:app --host 127.0.0.1 --port 8100 --log-level debug
```

### Service is running but not responding

```bash
# Test health endpoint
curl -v http://127.0.0.1:8100/health

# Test with API key
curl -H "X-ML-API-Key: your_key" http://127.0.0.1:8100/health

# Check service logs for errors
# Look for: [peanut-ml] ERROR messages in service output
```

### Predictions fail: "Model not yet trained"

```php
// Need to train first
$predictor = Peanut_Festival_ML_Predictor::get_instance();
$result = $predictor->train_model();

// Or trigger via cron
// Wait for weekly cron, or manually call the training endpoint:
// curl -X POST http://127.0.0.1:8100/festival/train -H "X-ML-API-Key: key"
```

### Database connection fails

```bash
# Check .env variables
echo $FESTIVAL_DB_HOST
echo $FESTIVAL_DB_NAME

# Test MySQL connection
mysql -h $FESTIVAL_DB_HOST -u $FESTIVAL_DB_USER -p$FESTIVAL_DB_PASS -D $FESTIVAL_DB_NAME -e "SELECT COUNT(*) FROM wp_peanut_festival_shows;"

# Check table names
mysql -h $FESTIVAL_DB_HOST -u $FESTIVAL_DB_USER -p$FESTIVAL_DB_PASS -D $FESTIVAL_DB_NAME -e "SHOW TABLES LIKE 'wp_peanut_festival_%';"
```

### Transient cache prevents fresh predictions

```php
// Clear prediction cache manually
$predictor = Peanut_Festival_ML_Predictor::get_instance();
$predictor->clear_prediction_cache();  // Private method, but can access

// Or use WordPress cache clearing
wp_cache_flush();

// Or delete specific transient
delete_transient('pf_ml_attendance_1');
```

## Common Issues & Solutions

| Issue | Cause | Solution |
|-------|-------|----------|
| "ML service unavailable" | Service not running on port 8100 | Start with: `uvicorn app:app --host 127.0.0.1 --port 8100` |
| "Model not yet trained" | No training data exists | Run: `curl -X POST http://127.0.0.1:8100/festival/train` |
| "Invalid API key" | Key doesn't match `ML_SERVICE_API_KEY` | Check .env and WordPress settings match |
| Empty confidence intervals | Not enough training data | Need at least 50 shows in database |
| Slow predictions | Cache expired | Predictions cached 15 min, first call may be slow |

## API Endpoints Reference

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/festival/predict-attendance` | POST | Predict show attendance |
| `/festival/optimize-schedule` | POST | Optimize scheduling |
| `/festival/recommend-vendor-placement` | POST | Vendor placement |
| `/festival/train` | POST | Train models |
| `/festival/stats` | GET | Model statistics |

**Base URL:** `http://127.0.0.1:8100`

**Headers Required:**
- `Content-Type: application/json`
- `X-ML-API-Key: your_key` (if configured)

## Performance Tips

1. **Cache Predictions** — Results cached 15 min automatically
2. **Batch Training** — Train weekly, not after each show
3. **Limit Historical Data** — Only uses last 12 months for training
4. **Use Health Checks** — Check `is_available()` before predictions
5. **Monitor Logs** — Check error_log for ML service errors

## Next Steps

1. Set up environment variables (see Configuration section)
2. Start ML service (see Start ML Service section)
3. Verify health: `curl http://127.0.0.1:8100/health`
4. Try first prediction (Example 1 above)
5. Integrate into your admin dashboard

## Documentation

For detailed information, see:
- **Full Docs:** `/sessions/hopeful-admiring-mayer/mnt/Peanut/PEANUT-FESTIVAL-WORDPRESS/docs/ML-INTEGRATION.md`
- **Summary:** `/sessions/hopeful-admiring-mayer/mnt/Peanut/ML-INTEGRATION-SUMMARY.md`
- **Checklist:** `/sessions/hopeful-admiring-mayer/mnt/Peanut/ML-INTEGRATION-CHECKLIST.md`

## Support

For issues:
1. Check `error_log()` output in WordPress
2. Check ML service logs (stdout when running)
3. Verify .env configuration
4. Test database connectivity
5. Verify API key if configured
6. Check service health: `curl http://127.0.0.1:8100/health`

---

**Ready to go!** Start with Example 1 above to predict your first show's attendance.
