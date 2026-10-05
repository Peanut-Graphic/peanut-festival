import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Dashboard } from './Dashboard';

// Mock the entire endpoints module
const mockGetStats = vi.fn();
vi.mock('@/api/endpoints', () => ({
  dashboardApi: {
    getStats: () => mockGetStats(),
  },
}));

const mockStats = {
  shows: {
    total: 10,
    scheduled: 5,
    completed: 3,
    cancelled: 2,
  },
  performers: {
    pending: 3,
    under_review: 2,
    accepted: 15,
    rejected: 1,
  },
  volunteers: {
    total_volunteers: 25,
    total_hours: 150,
    total_shifts: 20,
    total_slots: 100,
    filled_slots: 75,
  },
  tickets: {
    total_tickets: 200,
    checked_in: 150,
    total_revenue: 5000,
  },
  financials: {
    total_income: 10000,
    total_expenses: 3000,
    net: 7000,
  },
};

function createTestQueryClient() {
  return new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
        gcTime: 0,
      },
    },
  });
}

function renderWithProviders(ui: React.ReactElement) {
  const queryClient = createTestQueryClient();
  return render(
    <QueryClientProvider client={queryClient}>{ui}</QueryClientProvider>
  );
}

describe('Dashboard', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows loading state initially', () => {
    mockGetStats.mockImplementation(() => new Promise(() => {}));

    renderWithProviders(<Dashboard />);

    // Should show loading skeletons (4 cards)
    const skeletons = document.querySelectorAll('.animate-pulse .bg-gray-200');
    expect(skeletons.length).toBeGreaterThan(0);
  });

  it('renders dashboard title', async () => {
    mockGetStats.mockResolvedValue(mockStats);

    renderWithProviders(<Dashboard />);

    await waitFor(() => {
      expect(screen.getByText('Dashboard')).toBeInTheDocument();
    });
  });

  it('displays stat cards with correct values', async () => {
    mockGetStats.mockResolvedValue(mockStats);

    renderWithProviders(<Dashboard />);

    await waitFor(() => {
      expect(screen.getByText('Total Shows')).toBeInTheDocument();
      expect(screen.getByText('10')).toBeInTheDocument(); // total shows
      expect(screen.getByText('5 scheduled')).toBeInTheDocument();
    });
  });

  it('displays performer count', async () => {
    mockGetStats.mockResolvedValue(mockStats);

    renderWithProviders(<Dashboard />);

    await waitFor(() => {
      expect(screen.getByText('Performers')).toBeInTheDocument();
      // Total performers: 3 + 2 + 15 + 1 = 21
      expect(screen.getByText('21')).toBeInTheDocument();
    });
  });

  it('displays volunteer stats', async () => {
    mockGetStats.mockResolvedValue(mockStats);

    renderWithProviders(<Dashboard />);

    await waitFor(() => {
      expect(screen.getByText('Volunteers')).toBeInTheDocument();
      // 25 appears in both volunteer count and slots available, use getAllByText
      expect(screen.getAllByText('25').length).toBeGreaterThanOrEqual(1);
      expect(screen.getByText('150 hours logged')).toBeInTheDocument();
    });
  });

  it('renders all stat card labels', async () => {
    mockGetStats.mockResolvedValue(mockStats);

    const { container } = renderWithProviders(<Dashboard />);

    // Wait for loading to finish - checking for the stat card container
    await waitFor(() => {
      // Check that stat cards are rendered by looking for card elements
      const cards = container.querySelectorAll('.pf-card');
      expect(cards.length).toBeGreaterThanOrEqual(4);
    });
  });

  it('displays ticket sales section', async () => {
    mockGetStats.mockResolvedValue(mockStats);

    renderWithProviders(<Dashboard />);

    await waitFor(() => {
      expect(screen.getByText('Ticket Sales')).toBeInTheDocument();
      expect(screen.getByText('Total Tickets')).toBeInTheDocument();
      expect(screen.getByText('200')).toBeInTheDocument();
      expect(screen.getByText('Checked In')).toBeInTheDocument();
      expect(screen.getByText('150')).toBeInTheDocument();
    });
  });

  it('displays performer applications breakdown', async () => {
    mockGetStats.mockResolvedValue(mockStats);

    renderWithProviders(<Dashboard />);

    await waitFor(() => {
      expect(screen.getByText('Performer Applications')).toBeInTheDocument();
      expect(screen.getByText('Pending')).toBeInTheDocument();
      expect(screen.getByText('Under Review')).toBeInTheDocument();
      expect(screen.getByText('Accepted')).toBeInTheDocument();
    });
  });

  it('displays volunteer shifts section', async () => {
    mockGetStats.mockResolvedValue(mockStats);

    renderWithProviders(<Dashboard />);

    await waitFor(() => {
      expect(screen.getByText('Volunteer Shifts')).toBeInTheDocument();
      expect(screen.getByText('Total Shifts')).toBeInTheDocument();
      expect(screen.getByText('Slots Available')).toBeInTheDocument();
      expect(screen.getByText('Fill Rate')).toBeInTheDocument();
      expect(screen.getByText('75%')).toBeInTheDocument(); // 75/100 = 75%
    });
  });

  it('handles null stats gracefully', async () => {
    mockGetStats.mockResolvedValue(undefined);

    renderWithProviders(<Dashboard />);

    await waitFor(() => {
      expect(screen.getByText('Dashboard')).toBeInTheDocument();
    });

    // Should show 0 values
    const zeros = screen.getAllByText('0');
    expect(zeros.length).toBeGreaterThan(0);
  });

  it('shows "No upcoming shows" when scheduled is 0', async () => {
    const noShowsStats = {
      ...mockStats,
      shows: { ...mockStats.shows, scheduled: 0 },
    };
    mockGetStats.mockResolvedValue(noShowsStats);

    renderWithProviders(<Dashboard />);

    await waitFor(() => {
      expect(screen.getByText('No upcoming shows')).toBeInTheDocument();
    });
  });
});
