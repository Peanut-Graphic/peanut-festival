# Accessibility

Peanut Festival's commitment to accessibility and WCAG compliance.

---

## Our Commitment

Peanut Festival is committed to ensuring digital accessibility for people with disabilities. We continually improve the user experience for everyone by applying relevant accessibility standards to our festival and event management platform.

---

## Conformance Status

### WCAG 2.1 Compliance

Peanut Festival aims to conform to **WCAG 2.1 Level AA** standards.

| Principle | Status | Notes |
|-----------|--------|-------|
| **Perceivable** | Conforming | Alt text, color contrast, text sizing |
| **Operable** | Conforming | Keyboard navigation, skip links, no time traps |
| **Understandable** | Conforming | Clear labels, error identification, consistent navigation |
| **Robust** | Conforming | Screen reader compatible, semantic HTML |

### WordPress Accessibility Standards

Peanut Festival follows the [WordPress Accessibility Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/accessibility/) for all admin interface elements.

---

## Accessibility Features

### Event Calendar

- Calendar views use semantic table markup with proper headers
- Events are keyboard navigable (Tab, Enter, Arrow keys)
- Calendar navigation buttons have accessible labels
- Event details available without hover interactions
- Date cells announce their content to screen readers
- react-big-calendar configured with ARIA attributes

### Registration & Attendee Forms

- All form fields have associated `<label>` elements
- Required fields marked with both visual indicator and `aria-required`
- Validation errors linked to inputs via `aria-describedby`
- Error messages are descriptive and actionable
- Form groups use `<fieldset>` and `<legend>`
- react-hook-form integration maintains accessibility attributes

### Competition Management

- Competition status indicators use text + icon (not color alone)
- Scoring interfaces are keyboard accessible
- Entry lists use semantic table markup
- Drag-and-drop ordering (@dnd-kit) includes keyboard alternatives and ARIA announcements

### Visual

**Color Contrast:**
- Text meets 4.5:1 contrast ratio minimum
- UI elements meet 3:1 contrast ratio minimum
- Status indicators never rely solely on color

**Text Sizing:**
- Responsive text that scales with browser zoom
- No loss of functionality up to 200% zoom
- Supports browser font size settings

**Visual Indicators:**
- Focus states clearly visible on all interactive elements
- Status indicators use text labels alongside color
- Icons have text labels or accessible tooltips

### Keyboard Navigation

- All interactive elements are focusable
- Logical tab order throughout the interface
- Skip navigation links on all pages
- No keyboard traps
- Modal dialogs trap focus appropriately and return focus on close
- Calendar and date pickers are fully keyboard navigable

### Screen Reader Support

**Compatibility:**
- NVDA (Windows)
- JAWS (Windows)
- VoiceOver (macOS/iOS)
- TalkBack (Android)

**Implementation:**
- Semantic HTML structure with landmarks
- ARIA labels on interactive elements
- Descriptive link text (no "click here")
- Form field label associations
- Live regions for dynamic content updates (event status changes, form submissions)

---

## Testing

### Automated Testing

Our CI/CD pipeline includes:

1. **axe-core Integration** — Component-level WCAG violation detection via vitest + jest-axe
2. **ESLint jsx-a11y** — Static analysis of JSX for accessibility anti-patterns
3. **GitHub Actions** — Dedicated accessibility workflow runs on every pull request

### Running Tests Locally

```bash
# Run accessibility tests
cd frontend && npm run test:a11y

# Run via shell script
./scripts/a11y-check.sh

# Watch mode
./scripts/a11y-check.sh --watch

# Full test suite with coverage
npm run test:coverage
```

### Manual Testing

We periodically perform manual testing including:
- Screen reader navigation (VoiceOver, NVDA)
- Keyboard-only navigation of all flows
- Color contrast verification
- Calendar interaction with assistive technology
- Form completion with assistive technology

---

## Known Limitations

| Issue | Status | Timeline |
|-------|--------|----------|
| Calendar drag-and-drop requires keyboard alternative | Implemented | Complete |
| Complex chart accessibility descriptions | In progress | Q2 2026 |
| PDF export accessibility | Planned | Q3 2026 |

---

## Reporting Issues

Found an accessibility barrier?

1. **Email:** accessibility@peanutgraphic.com
2. **Include:**
   - What you were trying to do
   - What assistive technology you use
   - What happened vs. what you expected
   - Browser and OS version

We aim to respond within 5 business days.

---

## Resources

- [WebAIM](https://webaim.org/) — Web accessibility resources
- [W3C WAI](https://www.w3.org/WAI/) — Web Accessibility Initiative
- [A11Y Project](https://www.a11yproject.com/) — Community-driven accessibility
- [WordPress Accessibility Handbook](https://make.wordpress.org/accessibility/handbook/)

---

*Last updated: March 2026*
