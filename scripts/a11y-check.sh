#!/bin/bash
set -e

# Peanut Festival - Accessibility Testing Script
# Run accessibility tests for the Peanut Festival WordPress plugin

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
FRONTEND_DIR="$PROJECT_ROOT/frontend"

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Usage information
show_help() {
  cat << EOF
Peanut Festival - Accessibility Testing Tool

USAGE:
  ./scripts/a11y-check.sh [OPTIONS]

OPTIONS:
  --help, -h          Show this help message
  --watch, -w         Run tests in watch mode
  --coverage, -c      Generate coverage report
  --ui                Run with UI output
  --quiet, -q         Minimal output
  --verbose, -v       Verbose output

EXAMPLES:
  ./scripts/a11y-check.sh              # Run all accessibility tests
  ./scripts/a11y-check.sh --watch      # Run in watch mode (re-run on changes)
  ./scripts/a11y-check.sh --coverage   # Generate coverage report
  ./scripts/a11y-check.sh --ui         # Run with UI output
  ./scripts/a11y-check.sh --help       # Show this message

DESCRIPTION:
  This script runs automated accessibility testing on the Peanut Festival
  plugin frontend using jest-axe, ESLint jsx-a11y plugin, and Vitest.

  Tests check for WCAG 2.1 Level AA compliance including:
  - Proper heading hierarchy
  - ARIA attributes and live regions
  - Keyboard navigation support
  - Color contrast requirements
  - Form label associations
  - Table structure and semantics
  - Button and link accessibility
  - Modal focus management

REQUIREMENTS:
  - Node.js 20+
  - npm with dependencies installed (run: npm ci in frontend/)

ENVIRONMENT VARIABLES:
  VERBOSE            Set to 1 for verbose output
  WATCH              Set to 1 to enable watch mode
  COVERAGE           Set to 1 to generate coverage

EOF
}

# Parse command line arguments
WATCH_MODE=false
COVERAGE=false
QUIET=false
VERBOSE=false
UI_MODE=false

while [[ $# -gt 0 ]]; do
  case $1 in
    --help|-h)
      show_help
      exit 0
      ;;
    --watch|-w)
      WATCH_MODE=true
      shift
      ;;
    --coverage|-c)
      COVERAGE=true
      shift
      ;;
    --ui)
      UI_MODE=true
      shift
      ;;
    --quiet|-q)
      QUIET=true
      shift
      ;;
    --verbose|-v)
      VERBOSE=true
      shift
      ;;
    *)
      echo -e "${RED}Unknown option: $1${NC}"
      show_help
      exit 1
      ;;
  esac
done

# Check if frontend directory exists
if [ ! -d "$FRONTEND_DIR" ]; then
  echo -e "${RED}Error: Frontend directory not found at $FRONTEND_DIR${NC}"
  exit 1
fi

# Check if package.json exists
if [ ! -f "$FRONTEND_DIR/package.json" ]; then
  echo -e "${RED}Error: package.json not found in $FRONTEND_DIR${NC}"
  exit 1
fi

# Navigate to frontend directory
cd "$FRONTEND_DIR"

# Check if node_modules exists
if [ ! -d "node_modules" ]; then
  echo -e "${YELLOW}Dependencies not installed. Installing...${NC}"
  npm ci
fi

# Print header
echo ""
echo -e "${BLUE}╔═══════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║${NC} Peanut Festival - Accessibility Testing                    ${BLUE}║${NC}"
echo -e "${BLUE}╚═══════════════════════════════════════════════════════════════╝${NC}"
echo ""

# Build the test command
TEST_CMD="npm run test:a11y"

# Add flags
if [ "$WATCH_MODE" = true ]; then
  TEST_CMD="vitest -- --watch a11y"
  echo -e "${YELLOW}Running in WATCH mode (Ctrl+C to exit)${NC}"
fi

if [ "$COVERAGE" = true ]; then
  TEST_CMD="npm run test:coverage -- a11y"
  echo -e "${YELLOW}Coverage report will be generated${NC}"
fi

if [ "$UI_MODE" = true ]; then
  TEST_CMD="vitest --ui -- a11y"
  echo -e "${YELLOW}Opening Vitest UI...${NC}"
fi

if [ "$QUIET" = true ]; then
  echo -e "${YELLOW}Running in quiet mode${NC}"
fi

if [ "$VERBOSE" = true ]; then
  echo -e "${YELLOW}Running in verbose mode${NC}"
  TEST_CMD="$TEST_CMD --reporter=verbose"
fi

echo -e "${BLUE}Command:${NC} $TEST_CMD"
echo ""

# Step 1: Run ESLint with jsx-a11y plugin
if [ "$QUIET" != true ]; then
  echo -e "${BLUE}Step 1/2: Running ESLint with jsx-a11y plugin...${NC}"
fi

if npm run lint -- --ext ts,tsx > /dev/null 2>&1; then
  if [ "$QUIET" != true ]; then
    echo -e "${GREEN}✓ ESLint passed${NC}"
  fi
else
  echo -e "${RED}✗ ESLint failed${NC}"
  npm run lint -- --ext ts,tsx
  exit 1
fi

echo ""

# Step 2: Run accessibility tests
if [ "$QUIET" != true ]; then
  echo -e "${BLUE}Step 2/2: Running accessibility tests with axe-core and jest-axe...${NC}"
fi

eval "$TEST_CMD"
TEST_RESULT=$?

echo ""

if [ $TEST_RESULT -eq 0 ]; then
  echo -e "${GREEN}╔═══════════════════════════════════════════════════════════════╗${NC}"
  echo -e "${GREEN}║${NC} All accessibility tests passed! ✓                          ${GREEN}║${NC}"
  echo -e "${GREEN}╚═══════════════════════════════════════════════════════════════╝${NC}"
  echo ""
  exit 0
else
  echo -e "${RED}╔═══════════════════════════════════════════════════════════════╗${NC}"
  echo -e "${RED}║${NC} Some accessibility tests failed ✗                          ${RED}║${NC}"
  echo -e "${RED}╚═══════════════════════════════════════════════════════════════╝${NC}"
  echo ""
  echo -e "${YELLOW}Tips for fixing accessibility issues:${NC}"
  echo "  1. Check the test output above for specific violations"
  echo "  2. Review the ACCESSIBILITY.md documentation"
  echo "  3. Use the Vitest UI for interactive debugging: npm run test:a11y -- --ui"
  echo "  4. Run with --verbose flag for more details: ./scripts/a11y-check.sh --verbose"
  echo ""
  exit 1
fi
