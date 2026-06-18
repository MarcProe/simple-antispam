# Math CAPTCHA Plugin - UI Mockup

## Visual Representation

### ASCII Art Mockup of YOURLS Admin with Math CAPTCHA

```
+----------------------------------------------------------------------+
|  YOURLS: Your Own URL Shortener                                       |
+----------------------------------------------------------------------+
|  [Logo]                                                               |
+----------------------------------------------------------------------+
|  Home  |  URLs  |  Stats  |  Tools  |  Plugins  |  Settings        |
+----------------------------------------------------------------------+
|                                                                      |
|  +--------------------------------------------------------------+   |
|  |  Overall, tracking 156 links, 1,234 clicks, and counting!     |   |
|  +--------------------------------------------------------------+   |
|                                                                      |
|  +--------------------------------------------------------------+   |
|  |  Enter the URL: [https://example.com/long-url        ]     |   |
|  |                                                               |   |
|  |  Optional : Custom short URL: [mykeyword________]             |   |
|  |                                                               |   |
|  |  +--------------------------------------------------------+ |   |
|  |  | Math CAPTCHA: 25 + 37 = [______]                         | |   |
|  |  +--------------------------------------------------------+ |   |
|  |                                                               |   |
|  |  [ Shorten The URL ]                                            |   |
|  +--------------------------------------------------------------+   |
|                                                                      |
|  +--------------------------------------------------------------+   |
|  |  Short URL    |  URL                    |  Title    |  ...   |   |
|  +--------------------------------------------------------------+   |
|  |  abc          |  https://ex.com/long... |  Example  |  ...   |   |
|  |  def          |  https://ex.com/another |  Test    |  ...   |   |
|  +--------------------------------------------------------------+   |
|                                                                      |
+----------------------------------------------------------------------+
```

## Detailed UI Description

### CAPTCHA Field Styling

The Math CAPTCHA field appears as a distinct, styled element below the URL and keyword inputs:

**Visual Characteristics:**
- **Background**: Light yellow/orange (#fff8e1)
- **Border**: Thin orange border (#ffc107), 1px solid
- **Border Radius**: 4px rounded corners
- **Padding**: 10px internal spacing
- **Margin**: 10px top margin to separate from other fields

**Layout:**
```
+--------------------------------------------------+
| Math CAPTCHA: 25 + 37 = [______]                  |
+--------------------------------------------------+
```

### Field Components

1. **Label**: "Math CAPTCHA:" (bold text)
2. **Question**: Random addition problem (e.g., "25 + 37") in bold dark brown (#5d4037)
3. **Equals Sign**: " = " 
4. **Input Field**: 
   - ID: `math-captcha-answer`
   - Name: `math_captcha_answer`
   - Class: `text` (matches YOURLS styling)
   - Size: 10 characters wide
   - Placeholder: "Answer"
   - Type: text input

### Color Scheme

| Element | Color | Hex Code | Purpose |
|---------|-------|----------|---------|
| Background | Light Yellow | #fff8e1 | High visibility |
| Border | Amber | #ffc107 | Attention-grabbing |
| Question Text | Dark Brown | #5d4037 | Readability |
| Input Field | Default | - | Matches YOURLS theme |

### Behavior Flow

#### 1. Initial Page Load
```
Form displays with:
- URL input field (empty)
- Keyword input field (empty)
- Math CAPTCHA: "42 + 18 = " with empty answer field
- Shorten button enabled
```

#### 2. User Enters URL Only (Missing CAPTCHA)
```
User clicks "Shorten The URL"
→ Error message appears: "Please solve the math CAPTCHA to shorten URLs."
→ Form remains unchanged
→ CAPTCHA question stays the same
```

#### 3. User Enters Wrong Answer
```
User enters: URL = "https://example.com", Answer = "55"
Actual answer: 42 + 18 = 60
→ Error message appears: "Incorrect answer to the math question. Please try again."
→ Page reloads after 2 seconds
→ NEW CAPTCHA question appears (e.g., "15 + 23 = ")
```

#### 4. User Enters Correct Answer
```
User enters: URL = "https://example.com", Answer = "60"
→ Success! URL is shortened
→ New row appears in table: "abc | https://example.com | Example | ..."
→ CAPTCHA field clears (new question generated for next use)
→ Share box slides down with options
```

### Error Messages

The plugin displays two types of error messages via YOURLS' feedback system:

1. **Missing CAPTCHA**:
   ```
   Status: fail
   Message: "Please solve the math CAPTCHA to shorten URLs."
   ```

2. **Wrong Answer**:
   ```
   Status: fail
   Message: "Incorrect answer to the math question. Please try again."
   ```

Both messages appear in YOURLS' standard feedback notification bar at the top of the page.

### Responsive Behavior

The CAPTCHA field is fully responsive and follows YOURLS' form layout:
- On wide screens: Appears inline with other form elements
- On narrow screens: Wraps appropriately within the form container
- Maintains consistent spacing and alignment

### Example Questions

The plugin generates random addition problems with numbers between 1 and 99:

- Easy: "5 + 8 = "
- Medium: "25 + 37 = "
- Hard: "89 + 76 = "
- Edge cases: "1 + 99 = ", "99 + 99 = "

### Browser Compatibility

The CAPTCHA field works with:
- ✅ All modern browsers (Chrome, Firefox, Safari, Edge)
- ✅ Internet Explorer 11+ (with jQuery support)
- ✅ Mobile browsers (iOS Safari, Android Chrome)
- ✅ JavaScript must be enabled for AJAX submission

### Comparison: Before vs After

**Before (Standard YOURLS Form):**
```
+--------------------------------------------------+
| Enter the URL: [______________________]         |
| Optional : Custom short URL: [______]            |
| [ Shorten The URL ]                              |
+--------------------------------------------------+
```

**After (With Math CAPTCHA Plugin):**
```
+--------------------------------------------------+
| Enter the URL: [______________________]         |
| Optional : Custom short URL: [______]            |
|                                                  |
| +--------------------------------------------+ |  
| | Math CAPTCHA: 25 + 37 = [______]           | |  
| +--------------------------------------------+ |  
|                                                  |
| [ Shorten The URL ]                              |
+--------------------------------------------------+
```

---

## HTML Structure

```html
<div id="new_url">
    <div>
        <form id="new_url_form" action="" method="get">
            <div>
                <label for="add-url"><strong>Enter the URL</strong></label>:
                <input type="text" id="add-url" name="url" class="text" size="80" />
                <label for="add-keyword">Optional : <strong>Custom short URL</strong></label>:
                <input type="text" id="add-keyword" name="keyword" class="text" size="8" />
                <input type="hidden" name="nonce" value="..." />
                <input type="button" id="add-button" name="add-button" value="Shorten The URL" class="button" />
            </div>
        </form>
        <div id="feedback" style="display:none"></div>
    </div>
    
    <!-- Math CAPTCHA Field Added Here -->
    <div id="math-captcha-field">
        <label for="math-captcha-answer"><strong>Math CAPTCHA</strong></label>:
        <span id="math-captcha-question"> 25 + 37 = </span>
        <input type="text" id="math-captcha-answer" name="math_captcha_answer" 
               class="text" size="10" placeholder="Answer" />
    </div>
    
</div>
```

---

## CSS Styling

```css
#math-captcha-field {
    margin-top: 10px;
    padding: 10px;
    background: #fff8e1;
    border: 1px solid #ffc107;
    border-radius: 4px;
}

#math-captcha-question {
    font-weight: bold;
    color: #5d4037;
}

#math-captcha-answer {
    width: 80px;
    margin-left: 10px;
}
```

This creates a warm, attention-grabbing box that clearly separates the CAPTCHA from the rest of the form while maintaining a professional appearance that fits with YOURLS' admin interface.
