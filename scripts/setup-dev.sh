#!/bin/bash

# Setup script for LMU IoT Portal development environment

set -e

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${BLUE}🔧 Setting up LMU IoT Portal development environment...${NC}"
echo ""

# Make scripts executable (if not already)
echo -e "${BLUE}🔐 Setting executable permissions...${NC}"
chmod +x scripts/new-feature.sh
chmod +x scripts/platform-down.sh
chmod +x scripts/platform-up.sh
echo -e "${GREEN}✅ Permissions configured${NC}"

echo ""
echo -e "${GREEN}✨ Setup complete!${NC}"
echo ""
echo -e "${BLUE}📋 What's been configured:${NC}"
echo "  • Helper script: ./scripts/new-feature.sh"
echo "  • Platform scripts: ./scripts/platform-up.sh / ./scripts/platform-down.sh"
echo ""
echo -e "${BLUE}🚀 Quick start:${NC}"
echo "  1. Run: ${YELLOW}./scripts/new-feature.sh${NC} to create a new feature branch"
echo "  2. Make changes"
echo "  3. Commit: ${YELLOW}git commit -m \"fix: your message\"${NC}"
echo ""
echo -e "${BLUE}📚 Learn more:${NC}"
echo "  • Read CONTRIBUTING.md for complete workflow"
echo ""
