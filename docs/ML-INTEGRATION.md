# Peanut Festival ML Integration

Complete ML intelligence layer for festival management, integrated with the Peanut ML microservice (running at `http://127.0.0.1:8100`).

## Overview

The Festival Intelligence module provides three core capabilities:

1. **Attendance Prediction** — Predict show attendance based on venue capacity, timing, performer popularity, and historical data
2. **Schedule Optimization** — Recommend optimal time slots and venue assignments to maximize total attendance
3. **Vendor Placement Analytics** — Suggest vendor/sponsor placement zones based on predicted foot traffic patterns

All models are trained weekly from festival database tables using gradient boosting regression and cached with transient caching (15 minutes).

## Architecture

### Components

**ML Service (Python/FastAPI)**
- `/sessions/hopeful-admiring-mayer/mnt/Peanut/ML-SERVICE/services/festival_intelligence.py`
- 5 endpoints: predict-attendance, optimize-schedule, recommend-vendor-placement, train, stats
- Database: Reads from `wp_peanut_festival_*` tables
- Model persistence: joblib-cached models in `./models/` directory

**WordPress Plugin Integration (PHP)**
- `/sessions/hopeful-admiring-mayer/mnt/Peanut/PEANUT-FESTIVAL-WORDPRESS/includes/class-ml-festival-predictor.php`
- Singleton class with HTTP wrapper around ML service
- Transient caching (15 min) for predictions
- Weekly cron job for automatic model training
- Optional auto-prediction hook on show creation

**Wiring in Main Plugin**
- Loaded in `load_dependencies()` in `peanut-festival.php`
- Initialized in `run()` singleton
- Scheduled training cleared on deactivation

## Environment Configuration

Add these to your `.env` file for the ML service:

```bash
# Festival Database Connection (WordPress)
FESTIVAL_DB_HOST=localhost
FESTIVAL_DB_PORT=3306
FESTIVAL_DB_NAME=peanut_festival_wp
FESTIVAL_DB_USER=root
FESTIVAL_DB_PASS=password
FESTIVAL_DB_PREFIX=wp_

# ML Service API Key (optional, set empty for dev)
ML_SERVICE_API_KEY=your_secret_key

# Model storage directory
MODEL_DIR=./models
```

Add these to WordPress plugin settings (in `peanut_festival_settings` option):

```php
[
    'ml_enabled' => true,
    'ml_api_key' => 'your_secret_key',
    'ml_auto_train' => false,  // Auto-train weekly
]
```

## API Endpoints

All endpoints require `X-ML-API-Key` header if `ML_SERVICE_API_KEY` is configured.

### 1. Predict Attendance

**Endpoint:** `POST /festival/predict-attendance`

Predict show attendance based on venue and timing characteristics.

**Request:**
```json
{
  "festival_id": 1,
  "show_id": 5,
  "venue_capacity": 500,
  "day_of_week": 5,
  "time_of_day": 19,
  "performer_count": 2,
  "ticket_price": 25.0,
  "weather_forecast": "sunny",
  "historical_shows": 10
}
```

**Response:**
```json
{
  "predicted_attendance": 385,
  "confidence_interval": [335, 435],
  "capacity_utilization_pct": 77.0,
  "key_factors": [
    "Peak evening time boosts attendance",
    "Weekend slot attracts larger crowds"
  ],
  "model_version": "20260327_143022"
}
```

**Parameters:**
- `festival_id` (int, required): Festival ID
- `show_id` (int, optional): Show ID for reference
- `venue_capacity` (int, required): Venue capacity
- `day_of_week` (int, required): 0=Monday, 6=Sunday
- `time_of_day` (int, required): 0-23 hour
- `performer_count` (int, required): Number of performers
- `ticket_price` (float, required): Ticket price in dollars
- `weather_forecast` (string, optional): "sunny", "cloudy", "rainy", "snowy"
- `historical_shows` (int, optional): Count of past shows at venue

### 2. Optimize Schedule

**Endpoint:** `POST /festival/optimize-schedule`

Recommend optimal time slots and venue assignments.

**Request:**
```json
{
  "festival_id": 1,
  "shows": [
    {
      "performer_id": 10,
      "duration_minutes": 45,
      "popularity_score": 85.0
    },
    {
      "performer_id": 11,
      "duration_minutes": 60,
      "popularity_score": 72.0
    }
  ]
}
```

**Response:**
```json
{
  "schedule": [
    {
      "performer_id": 10,
      "venue_id": 1,
      "start_time": "19:00",
      "end_time": "19:45",
      "predicted_attendance": 425,
      "confidence": 0.85
    },
    {
      "performer_id": 11,
      "venue_id": 2,
      "start_time": "17:00",
      "end_time": "18:00",
      "predicted_attendance": 320,
      "confidence": 0.85
    }
  ],
  "total_predicted_attendance": 745,
  "overall_venue_utilization_pct": 74.5,
  "optimization_notes": [
    "Scheduled 2 shows across 2 venues",
    "Peak time slots assigned to highest-popularity performers",
    "Predicted total attendance: 745"
  ],
  "model_version": "20260327_143022"
}
```

### 3. Recommend Vendor Placement

**Endpoint:** `POST /festival/recommend-vendor-placement`

Suggest vendor and sponsor placement zones.

**Request:**
```json
{
  "festival_id": 1,
  "vendor_count": 12,
  "venue_layout_zones": [
    "entrance",
    "main_stage",
    "food_court",
    "exit"
  ]
}
```

**Response:**
```json
{
  "placements": [
    {
      "zone_name": "entrance",
      "vendor_ids": [1, 2, 3],
      "predicted_foot_traffic": 2000,
      "transaction_potential": 3000.0
    },
    {
      "zone_name": "main_stage",
      "vendor_ids": [4, 5, 6],
      "predicted_foot_traffic": 3000,
      "transaction_potential": 5400.0
    }
  ],
  "total_foot_traffic_estimate": 9000,
  "revenue_potential": 14400.0,
  "placement_rationale": {
    "strategy": "Maximize foot traffic to high-revenue zones",
    "entrance_priority": "First-time visitors, impulse purchases",
    "main_stage_priority": "Highest traffic during performances",
    "food_court_priority": "Meal times peak attendance"
  },
  "model_version": "20260327_143022"
}
```

### 4. Train Models

**Endpoint:** `POST /festival/train`

Train or retrain all models from database.

**Request:**
```json
{}
```

**Response:**
```json
{
  "status": "trained",
  "attendance_samples": 245,
  "schedule_samples": 245,
  "vendor_samples": 1523,
  "model_version": "20260327_143022"
}
```

Called automatically weekly. Can also trigger manually via WordPress admin.

### 5. Get Model Statistics

**Endpoint:** `GET /festival/stats`

Get current model version and platform statistics.

**Response:**
```json
{
  "model_version": "20260327_143022",
  "total_festivals": 8,
  "total_shows": 245,
  "avg_attendance_per_show": 287.5,
  "avg_venue_utilization": 71.2,
  "total_attendees": 4521,
  "total_check_ins": 6847
}
```

## WordPress Integration Usage

### Basic Usage

```php
// Get the predictor singleton
$predictor = Peanut_Festival_ML_Predictor::get_instance();

// Check if service is available
if (!$predictor->is_available()) {
    echo "ML service is not available";
    return;
}

// Predict attendance for a show
$prediction = $predictor->predict_attendance([
    'festival_id' => 1,
    'venue_capacity' => 500,
    'day_of_week' => 5,
    'time_of_day' => 19,
    'performer_count' => 2,
    'ticket_price' => 25.0,
]);

if (isset($prediction['error'])) {
    echo "Error: " . $prediction['error'];
} else {
    echo "Predicted attendance: " . $prediction['predicted_attendance'];
    echo " (" . $prediction['capacity_utilization_pct'] . "% capacity)";
}

// Optimize a festival schedule
$schedule = $predictor->optimize_schedule(1, [
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
]);

// Recommend vendor placement
$placement = $predictor->recommend_vendor_placement(1, [
    'vendor_count' => 12,
    'venue_layout_zones' => ['entrance', 'main_stage', 'food_court', 'exit'],
]);
```

### Scheduled Training

Training runs weekly automatically. To trigger manual training:

```php
$predictor = Peanut_Festival_ML_Predictor::get_instance();
$result = $predictor->train_model();

if (isset($result['error'])) {
    echo "Training failed: " . $result['error'];
} else {
    echo "Training completed: " . $result['attendance_samples'] . " shows processed";
}
```

### Check Model Statistics

```php
$stats = $predictor->get_model_stats();

echo "Model version: " . $stats['model_version'];
echo "Total festivals: " . $stats['total_festivals'];
echo "Average attendance: " . $stats['avg_attendance_per_show'];
```

### Auto-Prediction on Show Creation

Enable auto-prediction in plugin settings:

```php
// In your show creation code
$show_id = Peanut_Festival_Shows::create_show([
    'festival_id' => 1,
    'performer_id' => 10,
    'venue_id' => 1,
    'start_time' => '2026-04-15 19:00:00',
    'duration' => 45,
]);

// If enabled in settings, this will trigger:
Peanut_Festival_ML_Predictor::get_instance()->maybe_auto_predict_show($show_id);

// Prediction stored in post meta:
$prediction = get_post_meta($show_id, '_ml_attendance_prediction', true);
$confidence = get_post_meta($show_id, '_ml_prediction_confidence', true);
```

## Data Flow

### Training Pipeline

1. **Weekly Cron Job**
   - Triggered by WordPress cron (`peanut_festival_ml_train_weekly`)
   - Calls `Peanut_Festival_ML_Predictor::train_model()`

2. **Data Collection**
   - ML service queries `wp_peanut_festival_shows` (5000 last 12 months)
   - Fetches check-in counts from `wp_peanut_festival_check_ins`
   - Aggregates transaction data from `wp_peanut_festival_transactions`

3. **Feature Engineering**
   - Venue capacity and historical utilization
   - Performer popularity scores and appearance counts
   - Time of day and day of week factors
   - Weather impact (if provided)

4. **Model Training**
   - Gradient Boosting Regressor (100 estimators, depth 5)
   - StandardScaler for feature normalization
   - Models cached with joblib in `./models/`
   - Version timestamp recorded

5. **Cache Clearing**
   - All prediction transients cleared after training
   - Fresh predictions available for new scheduling

### Prediction Pipeline

1. **API Request**
   - WordPress calls ML service endpoint
   - HTTP request with X-ML-API-Key header
   - Request data validated

2. **Cache Check**
   - Prediction transient checked (15 min TTL)
   - Returns cached result if available

3. **Feature Preparation**
   - Input normalized to 0-1 scale
   - Time factors calculated from day/hour
   - Weather impact computed

4. **Model Prediction**
   - Scaled features passed to trained model
   - Regression output: predicted attendance
   - 95% confidence interval calculated
   - Utilization percentage computed

5. **Result Caching**
   - Result stored in transient (15 min)
   - Reduces ML service load
   - Returns to WordPress with metadata

### Scheduling Optimization

1. **Show Sorting**
   - Shows ranked by popularity score (descending)
   - High-popularity performers assigned prime slots

2. **Slot Assignment**
   - Time slots: 11:00, 13:00, 15:00, 17:00, 19:00, 21:00
   - Venues distributed round-robin
   - Attendance predicted for each slot

3. **Utilization Calculation**
   - Total capacity summed across venues
   - Total predicted attendance aggregated
   - Overall utilization percentage computed

### Vendor Placement

1. **Zone Analysis**
   - Zones ranked by foot traffic potential
   - Entrance → Main stage → Food court → Exit
   - Foot traffic estimates: 2000-3000 per zone

2. **Vendor Distribution**
   - Vendors allocated proportionally per zone
   - Transaction potential calculated per zone
   - Revenue recommendations provided

3. **Placement Rationale**
   - Strategic recommendations per zone type
   - Traffic patterns explained
   - Revenue potential highlighted

## Performance Considerations

### Caching Strategy

- **Prediction Cache**: 15 minutes (transient)
- **Model Loading**: Joblib in-memory (on-demand)
- **Database Queries**: Raw SQL with proper indexes
- **API Requests**: 30-second timeout, 2-second health check

### Optimization

- Feature engineering happens client-side (WordPress)
- Models pre-trained, only inference at prediction time
- Gradient Boosting handles feature importance automatically
- Batch processing of 5000 shows for training

### Scaling

- ML service designed for parallel predictions
- Database connection pooling via PyMySQL
- Transient caching reduces redundant ML calls
- Weekly training completes in <5 minutes

## Error Handling

### Service Unavailable

If ML service is not responding:

```php
if (!$predictor->is_available()) {
    // Fall back to default logic
    $predicted = $venue_capacity * 0.65;  // 65% utilization estimate
}
```

### Invalid Configuration

Check settings before making requests:

```php
$config = $predictor->get_ml_config();

if (!$config['enabled']) {
    // ML is disabled, skip
    return [];
}

if (empty($config['api_key'])) {
    // Warning: No API key configured (dev mode)
    logger->warning('ML service running in dev mode');
}
```

### Database Connection Errors

ML service logs detailed errors:

```
[peanut-ml.db] ERROR: Connection failed to FESTIVAL_DB_HOST
[peanut-ml.festival_intelligence] ERROR: No show data available for training
```

Check `.env` configuration if training fails.

## Development & Testing

### Running the ML Service

```bash
cd /sessions/hopeful-admiring-mayer/mnt/Peanut/ML-SERVICE
uvicorn app:app --host 127.0.0.1 --port 8100 --reload
```

### Health Check

```bash
curl http://127.0.0.1:8100/health
```

### Manual Training

```bash
curl -X POST http://127.0.0.1:8100/festival/train \
  -H "Content-Type: application/json" \
  -H "X-ML-API-Key: your_key"
```

### Testing WordPress Integration

```php
// In WordPress
$predictor = Peanut_Festival_ML_Predictor::get_instance();

echo $predictor->is_available() ? 'Service OK' : 'Service down';

$stats = $predictor->get_model_stats();
echo wp_json_encode($stats, JSON_PRETTY_PRINT);
```

### Test Data

Use festival database with at least:
- 50+ shows
- 10+ venues
- 100+ check-ins
- 500+ transactions

Training with less data will produce less accurate models.

## Integration Points

The ML service can be integrated at:

1. **Festival Admin Dashboard**
   - Display predicted attendance for upcoming shows
   - Show schedule optimization recommendations
   - Vendor placement suggestions

2. **Show Creation Workflow**
   - Auto-predict attendance when show is created
   - Suggest optimal time slot based on performer
   - Warn if scheduling conflicts detected

3. **Vendor/Sponsor Management**
   - Recommend premium zone placement
   - Show predicted foot traffic per zone
   - Track revenue impact post-festival

4. **Analytics Reports**
   - Compare predicted vs. actual attendance
   - Model accuracy metrics per venue
   - Seasonal patterns and trends

5. **Public-Facing Festival Pages**
   - Show estimated attendance for each show
   - Capacity bar with prediction confidence
   - "Likely to sell out" indicators

## Security Considerations

- ML service runs on localhost only (127.0.0.1:8100)
- API key authentication optional but recommended
- Database credentials in `.env` (not committed)
- No sensitive data in request/response bodies
- Transient cache doesn't expose predictions to other sites

## Future Enhancements

1. **Weather Integration**
   - Real weather API integration (WeatherAPI, OpenWeatherMap)
   - Historical weather correlation analysis

2. **Social Media Signals**
   - Monitor Twitter/Instagram mention velocity
   - Trending performer detection
   - Real-time demand forecasting

3. **Demand Surge Detection**
   - Real-time ticket sales spike detection
   - Dynamic availability warnings
   - Price optimization recommendations

4. **Performer Substitution Impact**
   - Quick re-optimization if performer cancels
   - Recommendation for replacement performers
   - Attendance impact estimation

5. **Multi-Festival Optimization**
   - Cross-festival scheduling optimization
   - Performer tour planning
   - Venue capacity planning across multiple events

## References

- ML Service: `/sessions/hopeful-admiring-mayer/mnt/Peanut/ML-SERVICE/services/festival_intelligence.py`
- WordPress Class: `/sessions/hopeful-admiring-mayer/mnt/Peanut/PEANUT-FESTIVAL-WORDPRESS/includes/class-ml-festival-predictor.php`
- Database Schema: `/sessions/hopeful-admiring-mayer/mnt/Peanut/PEANUT-FESTIVAL-WORDPRESS/includes/class-database.php`
- Plugin Entry: `/sessions/hopeful-admiring-mayer/mnt/Peanut/PEANUT-FESTIVAL-WORDPRESS/peanut-festival.php`
