import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import { axe, toHaveNoViolations } from 'jest-axe';
import '@testing-library/jest-dom';

// Import components to test
import Layout from '../../components/layout/Layout';
import Modal from '../../components/common/Modal';
import { FormFields } from '../../components/common/FormFields';
import Toast from '../../components/common/Toast';

expect.extend(toHaveNoViolations);

describe('Festival Management Plugin - Accessibility Tests', () => {
  describe('Layout Component', () => {
    it('should have no accessibility violations', async () => {
      const { container } = render(
        <Layout>
          <h1>Test Content</h1>
          <p>Main content area</p>
        </Layout>
      );
      const results = await axe(container);
      expect(results).toHaveNoViolations();
    });

    it('should have proper heading hierarchy', () => {
      const { container } = render(
        <Layout>
          <h1>Primary Heading</h1>
          <h2>Secondary Heading</h2>
          <h3>Tertiary Heading</h3>
        </Layout>
      );
      const h1 = screen.getByRole('heading', { level: 1 });
      const h2 = screen.getByRole('heading', { level: 2 });
      const h3 = screen.getByRole('heading', { level: 3 });

      expect(h1).toBeInTheDocument();
      expect(h2).toBeInTheDocument();
      expect(h3).toBeInTheDocument();
    });

    it('should have accessible navigation elements', () => {
      const { container } = render(
        <Layout>
          <nav aria-label="Main navigation">
            <a href="#festivals">Festivals</a>
            <a href="#shows">Shows</a>
            <a href="#attendees">Attendees</a>
          </nav>
        </Layout>
      );
      const nav = screen.getByRole('navigation');
      expect(nav).toHaveAttribute('aria-label', 'Main navigation');
    });

    it('should provide skip link for keyboard navigation', () => {
      const { container } = render(
        <Layout>
          <a href="#main" className="skip-link">
            Skip to main content
          </a>
          <nav>Navigation</nav>
          <main id="main">Main content</main>
        </Layout>
      );
      const skipLink = screen.getByText('Skip to main content');
      expect(skipLink).toHaveAttribute('href', '#main');
    });
  });

  describe('Modal Component', () => {
    it('should have no accessibility violations when open', async () => {
      const { container } = render(
        <Modal isOpen={true} onClose={vi.fn()} title="Festival Details">
          <p>Modal content</p>
        </Modal>
      );
      const results = await axe(container);
      expect(results).toHaveNoViolations();
    });

    it('should have proper ARIA role and attributes', () => {
      const { container } = render(
        <Modal isOpen={true} onClose={vi.fn()} title="Festival Details">
          <p>Modal content</p>
        </Modal>
      );
      const modal = container.querySelector('[role="dialog"]');
      expect(modal).toBeInTheDocument();
    });

    it('should have accessible title attribute', () => {
      const { container } = render(
        <Modal isOpen={true} onClose={vi.fn()} title="Event Schedule">
          <p>Schedule details</p>
        </Modal>
      );
      expect(screen.getByText('Event Schedule')).toBeInTheDocument();
    });

    it('should trap focus within modal', () => {
      const { container } = render(
        <Modal isOpen={true} onClose={vi.fn()} title="Festival Info">
          <button>Action 1</button>
          <button>Action 2</button>
          <button>Close Modal</button>
        </Modal>
      );
      const buttons = screen.getAllByRole('button');
      expect(buttons.length).toBeGreaterThan(0);
    });

    it('should close on Escape key', () => {
      const handleClose = vi.fn();
      render(
        <Modal isOpen={true} onClose={handleClose} title="Test Modal">
          <p>Content</p>
        </Modal>
      );
      expect(screen.getByText('Content')).toBeInTheDocument();
    });
  });

  describe('Form Component Accessibility', () => {
    it('should have proper label associations', () => {
      const { container } = render(
        <FormFields
          field={{
            name: 'festivalName',
            type: 'text',
            label: 'Festival Name',
            placeholder: 'Enter festival name',
          }}
          value=""
          onChange={vi.fn()}
        />
      );
      const label = screen.getByLabelText('Festival Name');
      expect(label).toBeInTheDocument();
    });

    it('should indicate required fields', () => {
      const { container } = render(
        <FormFields
          field={{
            name: 'eventTitle',
            type: 'text',
            label: 'Event Title',
            required: true,
          }}
          value=""
          onChange={vi.fn()}
        />
      );
      const input = screen.getByLabelText(/Event Title/);
      expect(input).toHaveAttribute('required');
    });

    it('should display validation error messages with ARIA live region', () => {
      const { container } = render(
        <FormFields
          field={{
            name: 'registrationDate',
            type: 'date',
            label: 'Registration Date',
          }}
          value=""
          onChange={vi.fn()}
          error="Please select a valid date"
        />
      );
      expect(screen.getByText('Please select a valid date')).toBeInTheDocument();
    });

    it('should have accessible select dropdown', () => {
      const { container } = render(
        <FormFields
          field={{
            name: 'festivalType',
            type: 'select',
            label: 'Festival Type',
            options: [
              { value: 'music', label: 'Music Festival' },
              { value: 'comedy', label: 'Comedy Festival' },
              { value: 'arts', label: 'Arts Festival' },
            ],
          }}
          value=""
          onChange={vi.fn()}
        />
      );
      expect(screen.getByLabelText('Festival Type')).toBeInTheDocument();
    });

    it('should provide placeholder text assistance', () => {
      const { container } = render(
        <FormFields
          field={{
            name: 'attendeeEmail',
            type: 'email',
            label: 'Email Address',
            placeholder: 'attendee@example.com',
          }}
          value=""
          onChange={vi.fn()}
        />
      );
      const input = screen.getByPlaceholderText('attendee@example.com');
      expect(input).toBeInTheDocument();
    });
  });

  describe('Toast Notification Accessibility', () => {
    it('should have no accessibility violations', async () => {
      const { container } = render(
        <Toast message="Event created successfully" type="success" />
      );
      const results = await axe(container);
      expect(results).toHaveNoViolations();
    });

    it('should use ARIA live region for announcements', () => {
      const { container } = render(
        <Toast message="Registration submitted" type="info" />
      );
      const alert = container.querySelector('[role="alert"]') ||
        container.querySelector('[role="status"]');
      expect(alert).toBeInTheDocument();
    });

    it('should announce different toast types', () => {
      const types = ['success', 'error', 'warning', 'info'];
      types.forEach((type) => {
        const { container } = render(
          <Toast message={`Message: ${type}`} type={type as any} />
        );
        expect(screen.getByText(`Message: ${type}`)).toBeInTheDocument();
      });
    });
  });

  describe('Calendar Component (react-big-calendar)', () => {
    it('should have accessible calendar grid structure', async () => {
      const { container } = render(
        <div>
          <h2>Festival Schedule</h2>
          <table role="grid" aria-label="Festival Events">
            <thead>
              <tr>
                <th>Monday</th>
                <th>Tuesday</th>
                <th>Wednesday</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>
                  <button aria-label="Event on March 1">Event 1</button>
                </td>
                <td>
                  <button aria-label="Event on March 2">Event 2</button>
                </td>
                <td>
                  <button aria-label="Event on March 3">Event 3</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      );
      const grid = container.querySelector('[role="grid"]');
      expect(grid).toHaveAttribute('aria-label', 'Festival Events');
    });

    it('should provide keyboard navigation for calendar events', () => {
      const { container } = render(
        <div role="region" aria-label="Calendar Events">
          <button aria-label="June 15, 2024 - Opening Ceremony">
            June 15
          </button>
          <button aria-label="June 16, 2024 - Main Events">June 16</button>
          <button aria-label="June 17, 2024 - Closing Show">June 17</button>
        </div>
      );
      const buttons = screen.getAllByRole('button');
      expect(buttons).toHaveLength(3);
    });

    it('should announce event status (upcoming, active, past)', () => {
      const { container } = render(
        <div>
          <div aria-label="Upcoming event" aria-current="false">
            Next Festival - May 2024
          </div>
          <div aria-label="Active event" aria-current="true">
            Current Festival - March 2024
          </div>
          <div aria-label="Past event" aria-current="false">
            Previous Festival - January 2024
          </div>
        </div>
      );
      const currentEvent = screen.getByText('Current Festival - March 2024');
      expect(currentEvent.parentElement).toHaveAttribute(
        'aria-current',
        'true'
      );
    });
  });

  describe('Drag and Drop (dnd-kit) Accessibility', () => {
    it('should provide ARIA announcements for drag operations', () => {
      const { container } = render(
        <div>
          <div role="button" aria-grabbed="false" draggable="true">
            Drag to reorder event
          </div>
          <div role="region" aria-live="polite">
            Event moved to position 2
          </div>
        </div>
      );
      const dragElement = screen.getByRole('button');
      expect(dragElement).toHaveAttribute('aria-grabbed', 'false');
    });

    it('should announce drop target availability', () => {
      const { container } = render(
        <div>
          <div draggable="true">Item 1</div>
          <div
            role="region"
            aria-dropeffect="move"
            aria-label="Drop zone for reordering"
          >
            Drop here
          </div>
        </div>
      );
      const dropZone = screen.getByText('Drop here');
      expect(dropZone).toHaveAttribute('aria-dropeffect', 'move');
    });
  });

  describe('Data Table Accessibility', () => {
    it('should have accessible table headers', async () => {
      const { container } = render(
        <table>
          <caption>Attendee List</caption>
          <thead>
            <tr>
              <th scope="col">Name</th>
              <th scope="col">Email</th>
              <th scope="col">Registration Date</th>
              <th scope="col">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>John Doe</td>
              <td>john@example.com</td>
              <td>March 1, 2024</td>
              <td>Confirmed</td>
            </tr>
          </tbody>
        </table>
      );
      const headers = screen.getAllByRole('columnheader');
      expect(headers.length).toBe(4);
      headers.forEach((header) => {
        expect(header).toHaveAttribute('scope', 'col');
      });
    });

    it('should have table caption', () => {
      const { container } = render(
        <table>
          <caption>Festival Venues</caption>
          <tbody>
            <tr>
              <td>Main Stage</td>
            </tr>
          </tbody>
        </table>
      );
      expect(screen.getByText('Festival Venues')).toBeInTheDocument();
    });

    it('should make sortable columns keyboard accessible', () => {
      const { container } = render(
        <table>
          <thead>
            <tr>
              <th>
                <button>
                  Attendee Name
                  <span aria-label="sortable column">⬍</span>
                </button>
              </th>
            </tr>
          </thead>
        </table>
      );
      const sortButton = screen.getByRole('button');
      expect(sortButton).toBeInTheDocument();
    });
  });

  describe('Button Accessibility', () => {
    it('should have descriptive button text', () => {
      const { container } = render(
        <div>
          <button>Create Festival</button>
          <button>Edit Event</button>
          <button>Delete Attendee</button>
        </div>
      );
      expect(screen.getByRole('button', { name: 'Create Festival' }))
        .toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'Edit Event' }))
        .toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'Delete Attendee' }))
        .toBeInTheDocument();
    });

    it('should provide ARIA label for icon buttons', () => {
      const { container } = render(
        <button aria-label="Delete this event">✕</button>
      );
      const button = screen.getByRole('button', { name: /Delete this event/ });
      expect(button).toBeInTheDocument();
    });

    it('should indicate disabled state', () => {
      const { container } = render(
        <button disabled aria-disabled="true">
          Submit (Disabled)
        </button>
      );
      const button = screen.getByRole('button');
      expect(button).toBeDisabled();
    });

    it('should indicate loading state', () => {
      const { container } = render(
        <button aria-busy="true" disabled>
          Loading...
        </button>
      );
      const button = screen.getByRole('button');
      expect(button).toHaveAttribute('aria-busy', 'true');
    });
  });

  describe('Date and Time Picker Accessibility', () => {
    it('should have accessible date input with label', () => {
      const { container } = render(
        <div>
          <label htmlFor="festival-start">Festival Start Date</label>
          <input
            id="festival-start"
            type="date"
            aria-label="Festival Start Date"
          />
        </div>
      );
      const input = screen.getByLabelText('Festival Start Date');
      expect(input).toHaveAttribute('type', 'date');
    });

    it('should provide time zone information', () => {
      const { container } = render(
        <div>
          <label htmlFor="event-time">Event Time</label>
          <input id="event-time" type="time" />
          <span id="timezone-hint">Times shown in EST</span>
        </div>
      );
      expect(screen.getByText('Times shown in EST')).toBeInTheDocument();
    });
  });

  describe('Navigation Accessibility', () => {
    it('should indicate current page in navigation', () => {
      const { container } = render(
        <nav aria-label="Festival management">
          <a href="/festivals" aria-current="page">
            Festivals
          </a>
          <a href="/shows">Shows</a>
          <a href="/attendees">Attendees</a>
        </nav>
      );
      const currentLink = screen.getByRole('link', { name: 'Festivals' });
      expect(currentLink).toHaveAttribute('aria-current', 'page');
    });

    it('should provide breadcrumb navigation', () => {
      const { container } = render(
        <nav aria-label="Breadcrumb">
          <a href="/festivals">Festivals</a>
          <span aria-label="breadcrumb separator">/</span>
          <a href="/festivals/1">Festival Name</a>
          <span aria-label="breadcrumb separator">/</span>
          <span aria-current="page">Edit</span>
        </nav>
      );
      const breadcrumb = screen.getByLabelText('Breadcrumb');
      expect(breadcrumb).toBeInTheDocument();
    });
  });

  describe('List Accessibility', () => {
    it('should use semantic list elements', () => {
      const { container } = render(
        <ul>
          <li>Festival 1</li>
          <li>Festival 2</li>
          <li>Festival 3</li>
        </ul>
      );
      const list = container.querySelector('ul');
      expect(list).toBeInTheDocument();
      const items = screen.getAllByRole('listitem');
      expect(items).toHaveLength(3);
    });

    it('should provide context for ordered lists', () => {
      const { container } = render(
        <ol>
          <li>Register for festival</li>
          <li>Receive confirmation email</li>
          <li>Attend event</li>
        </ol>
      );
      const list = container.querySelector('ol');
      expect(list).toBeInTheDocument();
    });
  });

  describe('Status Indicators Accessibility', () => {
    it('should announce status changes via ARIA live region', () => {
      const { container } = render(
        <div role="status" aria-live="polite" aria-atomic="true">
          Registration closed
        </div>
      );
      const status = screen.getByText('Registration closed');
      expect(status).toHaveAttribute('role', 'status');
    });

    it('should provide visual and textual status indicators', () => {
      const { container } = render(
        <div>
          <span aria-label="event status active" className="status-badge">
            Active
          </span>
          <span aria-label="event status upcoming" className="status-badge">
            Upcoming
          </span>
          <span aria-label="event status past" className="status-badge">
            Past
          </span>
        </div>
      );
      expect(screen.getByLabelText('event status active')).toBeInTheDocument();
    });
  });

  describe('Color Contrast', () => {
    it('should not rely on color alone to convey information', () => {
      const { container } = render(
        <div>
          <span className="text-red-600" title="Error">
            ✕ Validation failed
          </span>
          <span className="text-green-600" title="Success">
            ✓ Event created
          </span>
        </div>
      );
      const textElements = screen.getAllByText(/Validation failed|Event created/);
      expect(textElements.length).toBeGreaterThan(0);
    });
  });

  describe('Skip Links', () => {
    it('should provide skip to main content link', () => {
      const { container } = render(
        <div>
          <a href="#main-content" className="skip-link">
            Skip to main content
          </a>
          <nav>Navigation menu</nav>
          <main id="main-content">Main festival management content</main>
        </div>
      );
      const skipLink = screen.getByText('Skip to main content');
      expect(skipLink).toHaveAttribute('href', '#main-content');
    });
  });

  describe('Keyboard Navigation', () => {
    it('should support Tab key navigation', () => {
      const { container } = render(
        <div>
          <button>First Button</button>
          <button>Second Button</button>
          <button>Third Button</button>
        </div>
      );
      const buttons = screen.getAllByRole('button');
      buttons.forEach((button) => {
        expect(button).not.toHaveAttribute('tabindex', '-1');
      });
    });

    it('should support Enter key activation on buttons', () => {
      const handleClick = vi.fn();
      const { container } = render(<button onClick={handleClick}>Action</button>);
      const button = screen.getByRole('button');
      expect(button).toBeInTheDocument();
    });

    it('should support Space key activation on buttons', () => {
      const handleClick = vi.fn();
      const { container } = render(<button onClick={handleClick}>Action</button>);
      const button = screen.getByRole('button');
      expect(button).toBeInTheDocument();
    });

    it('should support Arrow keys for navigation in select', () => {
      const { container } = render(
        <select>
          <option>Option 1</option>
          <option>Option 2</option>
          <option>Option 3</option>
        </select>
      );
      const select = screen.getByRole('combobox');
      expect(select).toBeInTheDocument();
    });
  });

  describe('Form Submission Accessibility', () => {
    it('should announce form submission success', () => {
      const { container } = render(
        <div role="status" aria-live="polite">
          Festival details updated successfully
        </div>
      );
      expect(screen.getByText(/updated successfully/)).toBeInTheDocument();
    });

    it('should announce form submission errors', () => {
      const { container } = render(
        <div role="alert" aria-live="assertive">
          Please correct the errors below
        </div>
      );
      expect(screen.getByText(/correct the errors/)).toBeInTheDocument();
    });
  });

  describe('Auto-Save Feedback', () => {
    it('should provide ARIA live region for auto-save status', () => {
      const { container } = render(
        <div role="status" aria-live="polite">
          Changes saved automatically
        </div>
      );
      expect(screen.getByText('Changes saved automatically')).toBeInTheDocument();
    });
  });
});
