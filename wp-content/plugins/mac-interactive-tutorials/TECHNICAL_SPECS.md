# Technical Specifications - MAC Interactive Tutorials

## 📐 Database Schema

### Custom Post Type: `mac_tutorial`

**Table:** `wp_posts`
- `ID` - Post ID
- `post_title` - Tutorial title
- `post_content` - Tutorial description (WordPress editor)
- `post_type` - 'mac_tutorial'
- `post_status` - 'publish', 'draft', etc.

### Post Meta: `_mac_tutorial_steps`

**Table:** `wp_postmeta`
- `post_id` - Tutorial post ID
- `meta_key` - '_mac_tutorial_steps'
- `meta_value` - Serialized array:

```php
[
    [
        'id' => 'step_1',
        'title' => 'Step Title',
        'description' => 'Step description (HTML allowed)',
        'target_url' => 'admin.php?page=...',
        'target_selector' => '#element-id',
        'min_time' => 2, // minutes
        'max_time' => 5, // minutes
        'order' => 1
    ],
    // ... more steps
]
```

### Post Meta: `_mac_tutorial_settings`

**Table:** `wp_postmeta`
- `post_id` - Tutorial post ID
- `meta_key` - '_mac_tutorial_settings'
- `meta_value` - Serialized array:

```php
[
    'difficulty' => 'beginner|intermediate|advanced',
    'category' => 'Category name',
    'estimated_time' => '10-15', // minutes
]
```

### User Meta: `mac_tutorial_state`

**Table:** `wp_usermeta`
- `user_id` - User ID
- `meta_key` - 'mac_tutorial_state'
- `meta_value` - Serialized array:

```php
[
    'tutorial_id' => [
        'tutorial_id' => 123,
        'current_step' => 2,
        'status' => 'in-progress|pause|complete',
        'started_at' => '2025-01-01 10:00:00',
        'updated_at' => '2025-01-01 10:05:00',
        'completed_steps' => [0, 1], // Array of completed step indices
    ],
    // ... more tutorials
]
```

---

## 🏗️ Class Structure

### 1. Main Plugin Class

**File:** `mac-interactive-tutorials.php`

```php
class MAC_Interactive_Tutorials {
    const VERSION = '1.0.0';
    const PLUGIN_SLUG = 'mac-interactive-tutorials';
    
    private static $instance = null;
    
    public $post_type;
    public $meta_boxes;
    public $state_manager;
    public $frontend;
    
    public static function get_instance();
    private function __construct();
    public function init();
}
```

**Responsibilities:**
- Initialize plugin
- Load dependencies
- Register hooks

---

### 2. Post Type Class

**File:** `includes/class-post-type.php`

```php
class MAC_Tutorial_Post_Type {
    public function __construct();
    public function register_post_type();
    private function get_post_type_args();
}
```

**Methods:**
- `register_post_type()` - Register CPT
- `get_post_type_args()` - Get registration arguments

**Hooks:**
- `init` - Register post type

---

### 3. Meta Boxes Class

**File:** `includes/class-meta-boxes.php`

```php
class MAC_Tutorial_Meta_Boxes {
    public function __construct();
    public function add_meta_boxes();
    public function render_steps_meta_box($post);
    public function render_settings_meta_box($post);
    public function save_steps($post_id, $post);
    public function enqueue_admin_scripts($hook);
    private function get_steps($post_id);
    private function save_steps_data($post_id, $steps);
    private function save_settings_data($post_id, $settings);
}
```

**Methods:**
- `add_meta_boxes()` - Register meta boxes
- `render_steps_meta_box()` - Render steps builder
- `render_settings_meta_box()` - Render settings
- `save_steps()` - Save on post save
- `enqueue_admin_scripts()` - Load admin assets

**Hooks:**
- `add_meta_boxes` - Add meta boxes
- `save_post` - Save data
- `admin_enqueue_scripts` - Enqueue scripts

---

### 4. State Manager Class

**File:** `includes/class-state-manager.php`

```php
class MAC_Tutorial_State_Manager {
    private $nonce_key = 'mac_tutorial_state';
    
    public function __construct();
    public function get_user_state($user_id = null);
    public function get_active_tutorial($user_id = null);
    public function update_state($tutorial_id, $step_index, $status = 'in-progress');
    public function pause_tutorial($tutorial_id);
    public function resume_tutorial($tutorial_id);
    public function complete_tutorial($tutorial_id);
    public function handle_ajax();
    private function save_state($user_id, $state);
}
```

**Methods:**
- `get_user_state()` - Get all user states
- `get_active_tutorial()` - Get currently active tutorial
- `update_state()` - Update tutorial state
- `handle_ajax()` - AJAX handler

**AJAX Actions:**
- `mac_tutorial_state` - Handle state updates

**Hooks:**
- `wp_ajax_mac_tutorial_state` - AJAX handler

---

### 5. Frontend Class

**File:** `includes/class-frontend.php`

```php
class MAC_Tutorial_Frontend {
    public function __construct();
    public function enqueue_scripts($hook);
    public function render_widget_container();
    private function get_active_tutorial_data();
    private function should_load_widget();
}
```

**Methods:**
- `enqueue_scripts()` - Load frontend assets
- `render_widget_container()` - Render widget HTML
- `get_active_tutorial_data()` - Get tutorial data for JS

**Hooks:**
- `admin_enqueue_scripts` - Enqueue scripts
- `admin_footer` - Render widget container

---

### 6. Admin Class

**File:** `admin/class-admin.php`

```php
class MAC_Tutorial_Admin {
    public function __construct();
    public function add_admin_page();
    public function render_list_page();
    private function get_tutorials();
    private function get_tutorial_stats($tutorial_id);
}
```

**Methods:**
- `add_admin_page()` - Add admin menu
- `render_list_page()` - Render tutorials list

**Hooks:**
- `admin_menu` - Add menu page

---

## 🔌 AJAX Endpoints

### 1. State Management

**Action:** `mac_tutorial_state`

**Request:**
```javascript
{
    action: 'mac_tutorial_state',
    action_type: 'start|update_step|pause|resume|complete',
    tutorial_id: 123,
    step_index: 2,
    nonce: '...'
}
```

**Response (Success):**
```json
{
    "success": true,
    "data": {
        "state": {
            "tutorial_id": 123,
            "current_step": 2,
            "status": "in-progress"
        }
    }
}
```

**Response (Error):**
```json
{
    "success": false,
    "data": {
        "message": "Error message"
    }
}
```

---

## 📡 JavaScript API

### Widget Class

**File:** `frontend/assets/js/widget.js`

```javascript
class MacTutorialWidget {
    constructor(data);
    init();
    createWidget();
    setupEvents();
    loadStep(stepIndex);
    nextStep();
    prevStep();
    pause();
    resume();
    complete();
    close();
    updateState(action, stepIndex);
    navigateToUrl(url);
    highlightElement(selector);
    setupDrag();
}
```

**Data Structure:**
```javascript
MacTutorialData = {
    tutorial: {
        id: 123,
        title: 'Tutorial Title',
        content: 'Description',
        steps: [...]
    },
    current_step: 0,
    ajax_url: '...',
    nonce: '...'
}
```

---

## 🎨 CSS Structure

### Admin Styles

**File:** `admin/assets/css/admin.css`

**Classes:**
- `.mac-tutorial-steps-builder` - Main container
- `.mac-step-item` - Individual step
- `.mac-step-header` - Step header
- `.mac-step-content` - Step content fields
- `.mac-add-step` - Add button
- `.mac-remove-step` - Remove button

### Frontend Widget Styles

**File:** `frontend/assets/css/widget.css`

**Classes:**
- `.mac-tutorial-widget` - Main widget
- `.mac-tutorial-widget__header` - Widget header
- `.mac-tutorial-widget__content` - Widget content
- `.mac-tutorial-widget__footer` - Widget footer
- `.mac-tutorial-highlight` - Highlighted element
- `.mac-progress-bar` - Progress bar
- `.mac-progress-fill` - Progress fill

---

## 🔐 Security Considerations

### Nonce Verification
- All AJAX requests require nonce
- Nonce key: `mac_tutorial_nonce`
- Generated per request

### Capability Checks
- Create tutorial: `edit_posts`
- Edit tutorial: `edit_post`
- Start tutorial: `read`
- Admin page: `manage_options`

### Input Sanitization
- Text fields: `sanitize_text_field()`
- Textarea: `wp_kses_post()`
- URLs: `esc_url_raw()`
- Numbers: `absint()`
- Arrays: Validate structure

### Output Escaping
- HTML: `esc_html()`
- Attributes: `esc_attr()`
- URLs: `esc_url()`
- JavaScript: `wp_json_encode()`

---

## 📦 File Structure

```
mac-interactive-tutorials/
├── mac-interactive-tutorials.php          # Main plugin file
├── includes/
│   ├── class-post-type.php                # CPT registration
│   ├── class-meta-boxes.php                # Meta boxes
│   ├── class-state-manager.php             # State management
│   ├── class-frontend.php                  # Frontend loader
│   └── class-ajax-handler.php              # AJAX handlers (optional)
├── admin/
│   ├── class-admin.php                     # Admin page
│   ├── assets/
│   │   ├── css/
│   │   │   └── admin.css
│   │   └── js/
│   │       └── admin.js
│   └── templates/
│       └── list-page.php
├── frontend/
│   ├── assets/
│   │   ├── css/
│   │   │   └── widget.css
│   │   └── js/
│   │       └── widget.js
│   └── templates/
│       └── widget.php
└── languages/
    └── mac-interactive-tutorials.pot       # Translation file
```

---

## 🔄 Data Flow

### Creating Tutorial
1. User creates new post (CPT)
2. Fills title & content (editor)
3. Adds steps in meta box
4. Saves post
5. Steps saved to `_mac_tutorial_steps` meta
6. Settings saved to `_mac_tutorial_settings` meta

### Starting Tutorial
1. User clicks "Start Tutorial"
2. AJAX call to update state
3. State saved to `mac_tutorial_state` user meta
4. Page reloads or navigates
5. Frontend class checks for active tutorial
6. Widget loads with tutorial data
7. JavaScript initializes widget
8. First step displayed

### Navigating Steps
1. User clicks "Next"
2. JavaScript updates current step
3. AJAX call to update state
4. Navigate to target URL
5. New page loads
6. Widget checks state
7. Loads next step

---

## 🧪 Testing Requirements

### Unit Tests
- [ ] Post type registration
- [ ] Meta box rendering
- [ ] State management
- [ ] Data saving/loading

### Integration Tests
- [ ] Create tutorial flow
- [ ] Start tutorial flow
- [ ] Navigate steps flow
- [ ] State persistence

### Browser Tests
- [ ] Chrome
- [ ] Firefox
- [ ] Safari
- [ ] Edge

### Security Tests
- [ ] Nonce verification
- [ ] Capability checks
- [ ] Input sanitization
- [ ] SQL injection prevention
- [ ] XSS prevention

---

## 📊 Performance Considerations

### Database Queries
- Cache tutorial data (transient)
- Minimize meta queries
- Use `get_post_meta()` efficiently

### JavaScript
- Lazy load widget
- Debounce resize events
- Cache DOM queries

### CSS
- Minify CSS
- Use efficient selectors
- Avoid expensive properties

---

## 🔧 Configuration

### Constants
```php
define('MAC_TUTORIALS_VERSION', '1.0.0');
define('MAC_TUTORIALS_PATH', plugin_dir_path(__FILE__));
define('MAC_TUTORIALS_URL', plugin_dir_url(__FILE__));
```

### Options
- `mac_tutorials_version` - Plugin version
- `mac_tutorials_settings` - Global settings (future)

---

## 📝 Code Standards

### PHP
- Follow WordPress Coding Standards
- Use namespaces (optional)
- Document all functions
- Use type hints (PHP 7+)

### JavaScript
- Use ES6+ features
- Follow WordPress JavaScript standards
- Use jQuery for WordPress compatibility
- Comment complex logic

### CSS
- Follow BEM naming convention
- Use WordPress admin color scheme
- Responsive design
- Mobile-first approach

---

**Last Updated:** 2025  
**Version:** 1.0.0

