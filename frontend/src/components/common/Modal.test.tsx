import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { Modal, ConfirmDialog } from './Modal';

describe('Modal', () => {
  const defaultProps = {
    isOpen: true,
    onClose: vi.fn(),
    title: 'Test Modal',
    children: <div>Modal content</div>,
  };

  beforeEach(() => {
    vi.clearAllMocks();
  });

  afterEach(() => {
    // Reset body overflow style
    document.body.style.overflow = '';
  });

  describe('rendering', () => {
    it('renders when isOpen is true', () => {
      render(<Modal {...defaultProps} />);
      expect(screen.getByText('Test Modal')).toBeInTheDocument();
      expect(screen.getByText('Modal content')).toBeInTheDocument();
    });

    it('does not render when isOpen is false', () => {
      render(<Modal {...defaultProps} isOpen={false} />);
      expect(screen.queryByText('Test Modal')).not.toBeInTheDocument();
    });

    it('renders title correctly', () => {
      render(<Modal {...defaultProps} title="Custom Title" />);
      expect(screen.getByText('Custom Title')).toBeInTheDocument();
    });

    it('renders children correctly', () => {
      render(
        <Modal {...defaultProps}>
          <p>Custom content</p>
        </Modal>,
      );
      expect(screen.getByText('Custom content')).toBeInTheDocument();
    });

    it('renders footer when provided', () => {
      render(
        <Modal {...defaultProps} footer={<button>Save</button>}>
          Content
        </Modal>,
      );
      expect(screen.getByRole('button', { name: 'Save' })).toBeInTheDocument();
    });

    it('does not render footer when not provided', () => {
      render(<Modal {...defaultProps} />);
      // Footer section should not exist - check by looking for the border-t class
      const footerSection = document.querySelector('.border-t.bg-gray-50');
      expect(footerSection).not.toBeInTheDocument();
    });
  });

  describe('sizes', () => {
    it('applies small size class', () => {
      render(<Modal {...defaultProps} size="sm" />);
      const modal = document.querySelector('.max-w-sm');
      expect(modal).toBeInTheDocument();
    });

    it('applies medium size class by default', () => {
      render(<Modal {...defaultProps} />);
      const modal = document.querySelector('.max-w-md');
      expect(modal).toBeInTheDocument();
    });

    it('applies large size class', () => {
      render(<Modal {...defaultProps} size="lg" />);
      const modal = document.querySelector('.max-w-lg');
      expect(modal).toBeInTheDocument();
    });

    it('applies xl size class', () => {
      render(<Modal {...defaultProps} size="xl" />);
      const modal = document.querySelector('.max-w-xl');
      expect(modal).toBeInTheDocument();
    });

    it('applies full size class', () => {
      render(<Modal {...defaultProps} size="full" />);
      const modal = document.querySelector('.max-w-4xl');
      expect(modal).toBeInTheDocument();
    });
  });

  describe('interactions', () => {
    it('calls onClose when close button is clicked', () => {
      render(<Modal {...defaultProps} />);
      const closeButton = screen.getByRole('button', { name: 'Close dialog' });
      fireEvent.click(closeButton);
      expect(defaultProps.onClose).toHaveBeenCalledTimes(1);
    });

    it('calls onClose when backdrop is clicked', () => {
      render(<Modal {...defaultProps} />);
      const backdrop = document.querySelector('.bg-black\\/50');
      fireEvent.click(backdrop!);
      expect(defaultProps.onClose).toHaveBeenCalledTimes(1);
    });

    it('calls onClose when Escape key is pressed', () => {
      render(<Modal {...defaultProps} />);
      fireEvent.keyDown(document, { key: 'Escape' });
      expect(defaultProps.onClose).toHaveBeenCalledTimes(1);
    });

    it('does not call onClose for other keys', () => {
      render(<Modal {...defaultProps} />);
      fireEvent.keyDown(document, { key: 'Enter' });
      expect(defaultProps.onClose).not.toHaveBeenCalled();
    });
  });

  describe('body overflow', () => {
    it('sets body overflow to hidden when modal opens', () => {
      render(<Modal {...defaultProps} />);
      expect(document.body.style.overflow).toBe('hidden');
    });

    it('resets body overflow when modal closes', () => {
      const { rerender } = render(<Modal {...defaultProps} />);
      expect(document.body.style.overflow).toBe('hidden');

      rerender(<Modal {...defaultProps} isOpen={false} />);
      expect(document.body.style.overflow).toBe('');
    });

    it('cleans up body overflow on unmount', () => {
      const { unmount } = render(<Modal {...defaultProps} />);
      expect(document.body.style.overflow).toBe('hidden');

      unmount();
      expect(document.body.style.overflow).toBe('');
    });
  });
});

describe('ConfirmDialog', () => {
  const defaultProps = {
    isOpen: true,
    onClose: vi.fn(),
    onConfirm: vi.fn(),
    title: 'Confirm Action',
    message: 'Are you sure you want to proceed?',
  };

  beforeEach(() => {
    vi.clearAllMocks();
  });

  afterEach(() => {
    document.body.style.overflow = '';
  });

  describe('rendering', () => {
    it('renders with title and message', () => {
      render(<ConfirmDialog {...defaultProps} />);
      expect(screen.getByText('Confirm Action')).toBeInTheDocument();
      expect(screen.getByText('Are you sure you want to proceed?')).toBeInTheDocument();
    });

    it('renders default button text', () => {
      render(<ConfirmDialog {...defaultProps} />);
      expect(screen.getByRole('button', { name: 'Cancel' })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'Confirm' })).toBeInTheDocument();
    });

    it('renders custom button text', () => {
      render(<ConfirmDialog {...defaultProps} confirmText="Delete" cancelText="Keep" />);
      expect(screen.getByRole('button', { name: 'Keep' })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'Delete' })).toBeInTheDocument();
    });

    it('shows loading state', () => {
      render(<ConfirmDialog {...defaultProps} isLoading />);
      expect(screen.getByRole('button', { name: 'Processing...' })).toBeInTheDocument();
    });
  });

  describe('variants', () => {
    it('applies danger variant by default', () => {
      render(<ConfirmDialog {...defaultProps} />);
      const confirmButton = screen.getByRole('button', { name: 'Confirm' });
      expect(confirmButton).toHaveClass('btn-danger');
    });

    it('applies warning variant', () => {
      render(<ConfirmDialog {...defaultProps} variant="warning" />);
      const confirmButton = screen.getByRole('button', { name: 'Confirm' });
      expect(confirmButton).toHaveClass('btn-warning');
    });

    it('applies info variant', () => {
      render(<ConfirmDialog {...defaultProps} variant="info" />);
      const confirmButton = screen.getByRole('button', { name: 'Confirm' });
      expect(confirmButton).toHaveClass('btn-primary');
    });
  });

  describe('interactions', () => {
    it('calls onClose when cancel is clicked', () => {
      render(<ConfirmDialog {...defaultProps} />);
      fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));
      expect(defaultProps.onClose).toHaveBeenCalledTimes(1);
    });

    it('calls onConfirm when confirm is clicked', () => {
      render(<ConfirmDialog {...defaultProps} />);
      fireEvent.click(screen.getByRole('button', { name: 'Confirm' }));
      expect(defaultProps.onConfirm).toHaveBeenCalledTimes(1);
    });

    it('disables buttons when loading', () => {
      render(<ConfirmDialog {...defaultProps} isLoading />);
      expect(screen.getByRole('button', { name: 'Cancel' })).toBeDisabled();
      expect(screen.getByRole('button', { name: 'Processing...' })).toBeDisabled();
    });
  });
});
