import { describe, it, expect } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import { Layout } from './Layout';

function renderWithRouter(ui: React.ReactElement) {
  return render(<BrowserRouter>{ui}</BrowserRouter>);
}

describe('Layout', () => {
  it('renders children content', () => {
    renderWithRouter(
      <Layout>
        <div data-testid="child-content">Test Content</div>
      </Layout>,
    );

    expect(screen.getByTestId('child-content')).toBeInTheDocument();
    expect(screen.getByText('Test Content')).toBeInTheDocument();
  });

  it('renders navigation links', () => {
    renderWithRouter(
      <Layout>
        <div>Content</div>
      </Layout>,
    );

    // Check for main navigation items
    expect(screen.getByText('Dashboard')).toBeInTheDocument();
    expect(screen.getByText('Festivals')).toBeInTheDocument();
    expect(screen.getByText('Shows')).toBeInTheDocument();
    expect(screen.getByText('Performers')).toBeInTheDocument();
    expect(screen.getByText('Venues')).toBeInTheDocument();
    expect(screen.getByText('Voting')).toBeInTheDocument();
    expect(screen.getByText('Volunteers')).toBeInTheDocument();
    expect(screen.getByText('Settings')).toBeInTheDocument();
  });

  it('renders sidebar with logo', () => {
    renderWithRouter(
      <Layout>
        <div>Content</div>
      </Layout>,
    );

    expect(screen.getByText('Festival')).toBeInTheDocument();
  });

  it('sidebar can be collapsed', () => {
    renderWithRouter(
      <Layout>
        <div>Content</div>
      </Layout>,
    );

    // Find collapse button and click it
    const collapseButton = document.querySelector('button[class*="hover:bg-gray-100"]');
    expect(collapseButton).toBeInTheDocument();

    fireEvent.click(collapseButton!);

    // When collapsed, nav text should be hidden (sidebar is w-16)
    const sidebar = document.querySelector('aside');
    expect(sidebar).toHaveClass('md:w-16');
  });

  it('sidebar expands when collapse button is clicked again', () => {
    renderWithRouter(
      <Layout>
        <div>Content</div>
      </Layout>,
    );

    const collapseButton = document.querySelector('button[class*="hover:bg-gray-100"]');

    // First click - collapse
    fireEvent.click(collapseButton!);
    expect(document.querySelector('aside')).toHaveClass('md:w-16');

    // Second click - expand
    fireEvent.click(collapseButton!);
    expect(document.querySelector('aside')).toHaveClass('md:w-56');
  });

  it('navigation links have correct href attributes', () => {
    renderWithRouter(
      <Layout>
        <div>Content</div>
      </Layout>,
    );

    const dashboardLink = screen.getByText('Dashboard').closest('a');
    expect(dashboardLink).toHaveAttribute('href', '/');

    const festivalsLink = screen.getByText('Festivals').closest('a');
    expect(festivalsLink).toHaveAttribute('href', '/festivals');

    const performersLink = screen.getByText('Performers').closest('a');
    expect(performersLink).toHaveAttribute('href', '/performers');

    const settingsLink = screen.getByText('Settings').closest('a');
    expect(settingsLink).toHaveAttribute('href', '/settings');
  });

  it('applies active class to current route', () => {
    // Render with BrowserRouter starting at /festivals
    window.history.pushState({}, '', '/festivals');

    renderWithRouter(
      <Layout>
        <div>Content</div>
      </Layout>,
    );

    const festivalsLink = screen.getByText('Festivals').closest('a');
    expect(festivalsLink).toHaveClass('bg-primary-50');
    expect(festivalsLink).toHaveClass('text-primary-700');
  });

  it('main content area is scrollable', () => {
    renderWithRouter(
      <Layout>
        <div>Content</div>
      </Layout>,
    );

    const main = document.querySelector('main');
    expect(main).toHaveClass('overflow-auto');
  });

  it('shows tooltip on collapsed sidebar items', () => {
    renderWithRouter(
      <Layout>
        <div>Content</div>
      </Layout>,
    );

    // Collapse sidebar
    const collapseButton = document.querySelector('button[class*="hover:bg-gray-100"]');
    fireEvent.click(collapseButton!);

    // Navigation items should have title attribute when collapsed
    const navLinks = document.querySelectorAll('nav a');
    navLinks.forEach((link) => {
      expect(link).toHaveAttribute('title');
    });
  });

  it('renders all navigation icons', () => {
    renderWithRouter(
      <Layout>
        <div>Content</div>
      </Layout>,
    );

    // Each nav item should have an icon (svg element)
    const navLinks = document.querySelectorAll('nav a');
    navLinks.forEach((link) => {
      const icon = link.querySelector('svg');
      expect(icon).toBeInTheDocument();
    });
  });
});
