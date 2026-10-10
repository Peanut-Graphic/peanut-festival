# Peanut Festival - REST API Reference

This document covers all public REST API endpoints provided by the Peanut Festival plugin.

## Base URL

All endpoints are prefixed with:

```
/wp-json/peanut-festival/v1/
```

Example: `https://yoursite.com/wp-json/peanut-festival/v1/events`

## Authentication

Public endpoints do not require authentication. Admin endpoints (not documented here) require WordPress authentication via cookies or application passwords.

---

## Endpoints

### Events / Schedule

#### Get Public Events

Retrieve published events/shows for a festival.

```
GET /events
```

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `festival_id` | integer | No | Filter by festival ID |

**Response:**

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "Opening Night Comedy Showcase",
      "description": "The best local comics kick off the festival!",
      "show_date": "2025-07-15",
      "start_time": "20:00:00",
      "end_time": "22:00:00",
      "venue_name": "Main Stage Theater",
      "venue_address": "123 Comedy Lane, City, ST 12345",
      "status": "on_sale",
      "featured": true,
      "kid_friendly": false
    }
  ]
}
```

**Response Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Show ID |
| `title` | string | Show title |
| `description` | string | Show description |
| `show_date` | string | Date (YYYY-MM-DD) |
| `start_time` | string | Start time (HH:MM:SS) |
| `end_time` | string | End time (HH:MM:SS) |
| `venue_name` | string | Venue name |
| `venue_address` | string | Venue address |
| `status` | string | `on_sale`, `sold_out`, or `scheduled` |
| `featured` | boolean | Featured show flag |
| `kid_friendly` | boolean | Family-friendly flag |

---

### Voting

#### Get Voting Status

Get current voting status and available performers for a show.

```
GET /vote/status/{show_slug}
```

**URL Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `show_slug` | string | The show's slug identifier |

**Response:**

```json
{
  "success": true,
  "data": {
    "active_group": "semifinals",
    "is_open": true,
    "time_remaining": 3600,
    "performers": [
      {
        "id": 1,
        "name": "Jane Comic",
        "bio": "Stand-up comedian from Chicago...",
        "photo_url": "https://example.com/photos/jane.jpg"
      }
    ],
    "hide_bios": false
  }
}
```

**Response Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `active_group` | string | Current voting round (e.g., `pool`, `semifinals`, `finals`) |
| `is_open` | boolean | Whether voting is currently open |
| `time_remaining` | integer | Seconds until voting closes |
| `performers` | array | List of performers available to vote for |
| `hide_bios` | boolean | Whether bios should be hidden |

#### Submit Vote

Submit votes for performers.

```
POST /vote/submit
```

**Request Body:**

```json
{
  "show_slug": "summer-fest-voting",
  "performer_ids": [3, 7, 12],
  "token": "unique-browser-token-abc123"
}
```

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `show_slug` | string | Yes | Show slug identifier |
| `performer_ids` | array | Yes | Performer IDs in rank order: 1–3 distinct IDs, all in the show's active voting group (repeats collapse to their first rank; anything else returns `400 invalid_ballot`) |
| `token` | string | Yes | Unique identifier for vote deduplication |

**Success Response:**

```json
{
  "success": true,
  "message": "Vote recorded successfully"
}
```

**Error Responses:**

Missing parameters (400):
```json
{
  "success": false,
  "message": "Missing required parameters"
}
```

Voting closed (403):
```json
{
  "success": false,
  "message": "Voting is closed"
}
```

Already voted (403):
```json
{
  "success": false,
  "message": "You have already voted in this round"
}
```

---

### Performer Applications

#### Submit Performer Application

Submit a new performer application.

```
POST /apply/performer
```

**Request Body:**

```json
{
  "festival_id": 1,
  "name": "Jane Comic",
  "email": "jane@example.com",
  "phone": "555-123-4567",
  "bio": "I've been doing stand-up for 5 years...",
  "website": "https://janecomic.com",
  "performance_type": "comedy",
  "technical_requirements": "I need a wireless mic",
  "social_links": {
    "instagram": "@janecomic",
    "tiktok": "@janecomic",
    "youtube": "https://youtube.com/janecomic",
    "twitter": "@janecomic"
  }
}
```

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `festival_id` | integer | Yes | Festival ID |
| `name` | string | Yes | Performer/act name |
| `email` | string | Yes | Contact email |
| `phone` | string | No | Phone number |
| `bio` | string | No | Biography/description |
| `website` | string | No | Website URL |
| `performance_type` | string | No | Type: `comedy`, `improv`, `sketch`, `music`, `variety`, `other` |
| `technical_requirements` | string | No | Tech rider notes |
| `social_links` | object | No | Social media handles |

**Success Response:**

```json
{
  "success": true,
  "message": "Application submitted successfully",
  "data": {
    "id": 42
  }
}
```

**Error Responses:**

Missing required fields (400):
```json
{
  "success": false,
  "message": "Name and email are required"
}
```

Duplicate application (400):
```json
{
  "success": false,
  "message": "An application with this email already exists"
}
```

---

### Volunteer Signups

#### Get Available Shifts

Retrieve available volunteer shifts for a festival.

```
GET /volunteer/shifts/{festival_id}
```

**URL Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `festival_id` | integer | Festival ID |

**Response:**

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "task_name": "Box Office",
      "description": "Check in attendees and sell tickets",
      "location": "Main Entrance",
      "shift_date": "2025-07-15",
      "start_time": "17:00:00",
      "end_time": "22:00:00",
      "slots_available": 3
    }
  ]
}
```

**Response Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Shift ID |
| `task_name` | string | Task/role name |
| `description` | string | Task description |
| `location` | string | Where to report |
| `shift_date` | string | Date (YYYY-MM-DD) |
| `start_time` | string | Start time (HH:MM:SS) |
| `end_time` | string | End time (HH:MM:SS) |
| `slots_available` | integer | Remaining spots |

#### Submit Volunteer Signup

Submit a volunteer signup.

```
POST /volunteer/signup
```

**Request Body:**

```json
{
  "festival_id": 1,
  "name": "John Volunteer",
  "email": "john@example.com",
  "phone": "555-987-6543",
  "emergency_contact": "Jane Volunteer",
  "emergency_phone": "555-111-2222",
  "skills": ["hospitality", "tech", "driving"],
  "availability": [1, 3, 5],
  "shirt_size": "L",
  "dietary_restrictions": "Vegetarian"
}
```

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `festival_id` | integer | Yes | Festival ID |
| `name` | string | Yes | Full name |
| `email` | string | Yes | Contact email |
| `phone` | string | No | Phone number |
| `emergency_contact` | string | No | Emergency contact name |
| `emergency_phone` | string | No | Emergency contact phone |
| `skills` | array | No | Array of skill tags |
| `availability` | array | No | Array of preferred shift IDs |
| `shirt_size` | string | No | T-shirt size |
| `dietary_restrictions` | string | No | Food restrictions |

**Skill Options:**
- `hospitality` - Hospitality / Customer Service
- `tech` - Tech / AV Equipment
- `photography` - Photography / Video
- `social_media` - Social Media
- `driving` - Has Valid Driver's License
- `setup` - Setup / Breakdown
- `box_office` - Box Office / Will Call
- `green_room` - Green Room / Performer Support

**Success Response:**

```json
{
  "success": true,
  "message": "Volunteer signup submitted successfully",
  "data": {
    "id": 28
  }
}
```

---

### Vendor Applications

#### Submit Vendor Application

Submit a vendor booth application.

```
POST /apply/vendor
```

**Request Body:**

```json
{
  "festival_id": 1,
  "business_name": "Funny Merch Co",
  "contact_name": "Bob Vendor",
  "email": "bob@funnymerch.com",
  "phone": "555-555-5555",
  "vendor_type": "merchandise",
  "description": "We sell comedy-themed t-shirts and accessories",
  "products": "T-shirts, hats, mugs, stickers",
  "booth_requirements": "10x10 space, 2 tables",
  "electricity_needed": true
}
```

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `festival_id` | integer | Yes | Festival ID |
| `business_name` | string | Yes | Business/company name |
| `contact_name` | string | No | Primary contact person |
| `email` | string | Yes | Contact email |
| `phone` | string | No | Phone number |
| `vendor_type` | string | Yes | Category (see below) |
| `description` | string | No | Business description |
| `products` | string | No | Products/services offered |
| `booth_requirements` | string | No | Setup requirements |
| `electricity_needed` | boolean | No | Needs power |

**Vendor Types:**
- `food` - Food & Beverage
- `merchandise` - Merchandise / Retail
- `service` - Service Provider
- `sponsor` - Sponsor / Promotional
- `other` - Other

**Success Response:**

```json
{
  "success": true,
  "message": "Vendor application submitted successfully",
  "data": {
    "id": 15
  }
}
```

---

### Flyer Templates

#### Get Flyer Templates

Retrieve available flyer templates for a festival.

```
GET /flyer/templates/{festival_id}
```

**URL Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `festival_id` | integer | Festival ID |

**Response:**

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Main Festival Flyer",
      "template_url": "https://example.com/templates/main.png",
      "mask_url": "https://example.com/templates/main-mask.png",
      "frame": {
        "x": 50,
        "y": 100,
        "width": 400,
        "height": 400
      },
      "namebox": {
        "x": 250,
        "y": 520,
        "fontSize": 36,
        "color": "#ffffff"
      },
      "title": "Summer Comedy Fest",
      "subtitle": "July 15-20, 2025"
    }
  ]
}
```

**Response Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Template ID |
| `name` | string | Template name |
| `template_url` | string | URL to template image |
| `mask_url` | string | URL to mask image (for cutout effect) |
| `frame` | object | Photo frame position/size |
| `namebox` | object | Name text position/style |
| `title` | string | Festival title on template |
| `subtitle` | string | Festival subtitle/dates |

---

## Error Handling

All endpoints return consistent error responses:

**Standard Error Format:**

```json
{
  "success": false,
  "message": "Human-readable error message"
}
```

**HTTP Status Codes:**

| Code | Description |
|------|-------------|
| 200 | Success |
| 400 | Bad Request - Missing or invalid parameters |
| 403 | Forbidden - Action not allowed (e.g., voting closed) |
| 404 | Not Found - Resource doesn't exist |
| 500 | Server Error - Database or processing error |

---

## Rate Limiting

The API does not implement rate limiting at the application level. Consider implementing rate limiting at the server level (nginx, Cloudflare, etc.) for production use.

---

## CORS

Cross-origin requests are handled by WordPress's default REST API CORS headers. For custom frontend applications, you may need to configure additional headers.

---

## Examples

### JavaScript Fetch

```javascript
// Submit a vote
async function submitVote(showSlug, performerIds, token) {
  const response = await fetch('/wp-json/peanut-festival/v1/vote/submit', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      show_slug: showSlug,
      performer_ids: performerIds,
      token: token,
    }),
  });

  return response.json();
}

// Get events
async function getEvents(festivalId) {
  const url = new URL('/wp-json/peanut-festival/v1/events', window.location.origin);
  if (festivalId) {
    url.searchParams.set('festival_id', festivalId);
  }

  const response = await fetch(url);
  return response.json();
}
```

### jQuery AJAX

```javascript
// Submit performer application
jQuery.ajax({
  url: '/wp-json/peanut-festival/v1/apply/performer',
  method: 'POST',
  contentType: 'application/json',
  data: JSON.stringify({
    festival_id: 1,
    name: 'Jane Comic',
    email: 'jane@example.com',
    bio: 'Stand-up comedian...',
  }),
  success: function(response) {
    if (response.success) {
      console.log('Application submitted:', response.data.id);
    }
  },
  error: function(xhr) {
    console.error('Error:', xhr.responseJSON.message);
  }
});
```

### cURL

```bash
# Get events
curl -X GET "https://yoursite.com/wp-json/peanut-festival/v1/events?festival_id=1"

# Submit volunteer signup
curl -X POST "https://yoursite.com/wp-json/peanut-festival/v1/volunteer/signup" \
  -H "Content-Type: application/json" \
  -d '{
    "festival_id": 1,
    "name": "John Volunteer",
    "email": "john@example.com",
    "skills": ["hospitality", "tech"]
  }'
```

---

## Changelog

### Version 1.0.0

- Initial API release
- Public endpoints for events, voting, applications
- No breaking changes planned for 1.x releases
