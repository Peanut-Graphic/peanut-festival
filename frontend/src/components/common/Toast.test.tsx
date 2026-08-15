import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { ToastProvider, useToast } from './Toast';

// Test component that uses the toast hook
function TestComponent() {
  const { addToast, removeToast, toasts } = useToast();

  return (
    <div>
      <button onClick={() => addToast('success', 'Success message')}>Success</button>
      <button onClick={() => addToast('error', 'Error message')}>Error</button>
      <button onClick={() => addToast('warning', 'Warning message')}>Warning</button>
      <button onClick={() => addToast('info', 'Info message')}>Info</button>
      <button onClick={() => addToast('success', 'Persistent', 0)}>Persistent</button>
      {toasts.length > 0 && <button onClick={() => removeToast(toasts[0].id)}>Remove First</button>}
      <span data-testid="toast-count">{toasts.length}</span>
    </div>
  );
}

describe('Toast', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('ToastProvider', () => {
    it('renders children', () => {
      render(
        <ToastProvider>
          <div>Test Content</div>
        </ToastProvider>,
      );
      expect(screen.getByText('Test Content')).toBeInTheDocument();
    });

    it('provides toast context to children', () => {
      render(
        <ToastProvider>
          <TestComponent />
        </ToastProvider>,
      );
      // Should render without throwing
      expect(screen.getByText('Success')).toBeInTheDocument();
    });
  });

  describe('useToast hook', () => {
    it('throws error when used outside ToastProvider', () => {
      // Suppress console.error for this test
      const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => {});

      expect(() => {
        render(<TestComponent />);
      }).toThrow('useToast must be used within a ToastProvider');

      consoleSpy.mockRestore();
    });
  });

  describe('addToast', () => {
    it('adds a success toast', () => {
      render(
        <ToastProvider>
          <TestComponent />
        </ToastProvider>,
      );

      fireEvent.click(screen.getByText('Success'));
      expect(screen.getByText('Success message')).toBeInTheDocument();
    });

    it('adds an error toast', () => {
      render(
        <ToastProvider>
          <TestComponent />
        </ToastProvider>,
      );

      fireEvent.click(screen.getByText('Error'));
      expect(screen.getByText('Error message')).toBeInTheDocument();
    });

    it('adds a warning toast', () => {
      render(
        <ToastProvider>
          <TestComponent />
        </ToastProvider>,
      );

      fireEvent.click(screen.getByText('Warning'));
      expect(screen.getByText('Warning message')).toBeInTheDocument();
    });

    it('adds an info toast', () => {
      render(
        <ToastProvider>
          <TestComponent />
        </ToastProvider>,
      );

      fireEvent.click(screen.getByText('Info'));
      expect(screen.getByText('Info message')).toBeInTheDocument();
    });

    it('can add multiple toasts', () => {
      render(
        <ToastProvider>
          <TestComponent />
        </ToastProvider>,
      );

      fireEvent.click(screen.getByText('Success'));
      fireEvent.click(screen.getByText('Error'));
      fireEvent.click(screen.getByText('Warning'));

      expect(screen.getByText('Success message')).toBeInTheDocument();
      expect(screen.getByText('Error message')).toBeInTheDocument();
      expect(screen.getByText('Warning message')).toBeInTheDocument();
      expect(screen.getByTestId('toast-count')).toHaveTextContent('3');
    });
  });

  describe('removeToast', () => {
    it('removes a toast by id', () => {
      render(
        <ToastProvider>
          <TestComponent />
        </ToastProvider>,
      );

      fireEvent.click(screen.getByText('Success'));
      expect(screen.getByText('Success message')).toBeInTheDocument();

      fireEvent.click(screen.getByText('Remove First'));
      expect(screen.queryByText('Success message')).not.toBeInTheDocument();
    });

    it('removes toast when close button is clicked', () => {
      render(
        <ToastProvider>
          <TestComponent />
        </ToastProvider>,
      );

      fireEvent.click(screen.getByText('Success'));
      expect(screen.getByText('Success message')).toBeInTheDocument();

      // Find the close button within the toast
      const toast = screen.getByText('Success message').closest('div');
      const closeButton = toast?.querySelector('button');
      expect(closeButton).toBeInTheDocument();

      fireEvent.click(closeButton!);
      expect(screen.queryByText('Success message')).not.toBeInTheDocument();
    });
  });

  describe('toast styling', () => {
    it('applies success styles', () => {
      render(
        <ToastProvider>
          <TestComponent />
        </ToastProvider>,
      );

      fireEvent.click(screen.getByText('Success'));
      const toast = screen.getByText('Success message').closest('div');
      expect(toast).toHaveClass('bg-green-50', 'border-green-200');
    });

    it('applies error styles', () => {
      render(
        <ToastProvider>
          <TestComponent />
        </ToastProvider>,
      );

      fireEvent.click(screen.getByText('Error'));
      const toast = screen.getByText('Error message').closest('div');
      expect(toast).toHaveClass('bg-red-50', 'border-red-200');
    });

    it('applies warning styles', () => {
      render(
        <ToastProvider>
          <TestComponent />
        </ToastProvider>,
      );

      fireEvent.click(screen.getByText('Warning'));
      const toast = screen.getByText('Warning message').closest('div');
      expect(toast).toHaveClass('bg-yellow-50', 'border-yellow-200');
    });

    it('applies info styles', () => {
      render(
        <ToastProvider>
          <TestComponent />
        </ToastProvider>,
      );

      fireEvent.click(screen.getByText('Info'));
      const toast = screen.getByText('Info message').closest('div');
      expect(toast).toHaveClass('bg-blue-50', 'border-blue-200');
    });
  });

  describe('toast container', () => {
    it('renders toast container with correct positioning', () => {
      render(
        <ToastProvider>
          <TestComponent />
        </ToastProvider>,
      );

      fireEvent.click(screen.getByText('Success'));
      const container = screen.getByText('Success message').closest('div')?.parentElement;
      expect(container).toHaveClass(
        'fixed',
        'inset-x-3',
        'bottom-3',
        'z-50',
        'sm:inset-x-auto',
        'sm:bottom-4',
        'sm:right-4',
      );
    });

    it('renders toasts with animation class', () => {
      render(
        <ToastProvider>
          <TestComponent />
        </ToastProvider>,
      );

      fireEvent.click(screen.getByText('Success'));
      const toast = screen.getByText('Success message').closest('div');
      expect(toast).toHaveClass('animate-slide-in');
    });
  });

  describe('toast icons', () => {
    it('renders success icon', () => {
      render(
        <ToastProvider>
          <TestComponent />
        </ToastProvider>,
      );

      fireEvent.click(screen.getByText('Success'));
      const toast = screen.getByText('Success message').closest('div');
      const icon = toast?.querySelector('svg');
      expect(icon).toBeInTheDocument();
      expect(icon).toHaveClass('text-green-500');
    });

    it('renders error icon', () => {
      render(
        <ToastProvider>
          <TestComponent />
        </ToastProvider>,
      );

      fireEvent.click(screen.getByText('Error'));
      const toast = screen.getByText('Error message').closest('div');
      const icon = toast?.querySelector('svg');
      expect(icon).toBeInTheDocument();
      expect(icon).toHaveClass('text-red-500');
    });

    it('renders warning icon', () => {
      render(
        <ToastProvider>
          <TestComponent />
        </ToastProvider>,
      );

      fireEvent.click(screen.getByText('Warning'));
      const toast = screen.getByText('Warning message').closest('div');
      const icon = toast?.querySelector('svg');
      expect(icon).toBeInTheDocument();
      expect(icon).toHaveClass('text-yellow-500');
    });

    it('renders info icon', () => {
      render(
        <ToastProvider>
          <TestComponent />
        </ToastProvider>,
      );

      fireEvent.click(screen.getByText('Info'));
      const toast = screen.getByText('Info message').closest('div');
      const icon = toast?.querySelector('svg');
      expect(icon).toBeInTheDocument();
      expect(icon).toHaveClass('text-blue-500');
    });
  });

  describe('toast persistence', () => {
    it('can create a persistent toast with duration 0', () => {
      render(
        <ToastProvider>
          <TestComponent />
        </ToastProvider>,
      );

      fireEvent.click(screen.getByRole('button', { name: 'Persistent' }));
      // Both button and toast have "Persistent" text, so use getAllByText
      const elements = screen.getAllByText('Persistent');
      expect(elements.length).toBe(2); // Button + toast message
      expect(screen.getByTestId('toast-count')).toHaveTextContent('1');
    });
  });
});
