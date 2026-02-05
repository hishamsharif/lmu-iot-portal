# Contributing to LMU IoT Portal

## 🔄 Git Workflow

This project follows a **streamlined git-flow** workflow with automated enforcement via GitHub Actions.

### Branch Strategy

- **`main`** - Production-ready code. All PRs merge here.
- **Feature branches** - Short-lived branches for individual user stories

### Working on a User Story

#### 1️⃣ Pick an Issue
Select an issue from the GitHub Project board (preferably from "Ready" or "Backlog" columns).

#### 2️⃣ Create Feature Branch
```bash
# Format: feature/us-<issue-number>-<short-slug>
git checkout main
git pull origin main
git checkout -b feature/us-1-device-types
```

**Branch naming rules** (enforced by CI):
- Must start with `feature/` or `hotfix/`
- Must include `us-<issue-number>`
- Must use kebab-case slug
- Example: `feature/us-7-parameter-definitions`

#### 3️⃣ Commit Your Work
All commits **must** follow this format (enforced by CI):

```
US-<issue-number>: <short description> #<issue-number>
```

**Examples:**
```bash
git commit -m "US-1: Add ProtocolType enum #1"
git commit -m "US-1: Create ProtocolConfigCast for MQTT/HTTP #1"
git commit -m "US-1: Add DeviceType model and migration #1"
```

**Why include `#<issue-number>`?**
- Creates automatic GitHub issue linking
- Allows viewing all commits for an issue directly from GitHub
- Enables better traceability in GitHub's UI

**Why?** This links commits to issues, making the history traceable and enabling automated changelog generation.

#### 4️⃣ Push and Open PR
```bash
git push origin feature/us-1-device-types
```

Then open a PR on GitHub:
- **Base branch**: `main`
- **Title**: Should include `US-<issue-number>` (e.g., "US-1: Device Type Management")
- **Description**: Use the PR template (auto-populated). Fill in the issue reference (`Closes #1`)

#### 5️⃣ Automated Checks
GitHub Actions will automatically:
- ✅ Validate commit message format (`US-<number>:` prefix)
- ✅ Validate branch name (`feature/us-<number>-<slug>`)
- ✅ Check PR references an issue
- ✅ Run tests, Pint, PHPStan (existing CI)

#### 6️⃣ Move Issue to "In Review"
Manually move the issue from "In Progress" → "In Review" on the GitHub Project board.

> **Future automation**: We can set up GitHub Project automation to do this automatically when PR is opened.

#### 7️⃣ Code Review
- If **changes requested**: Address feedback, push new commits (same format), move issue back to "In Progress"
- If **approved**: Merge the PR (use **Squash and Merge** for clean history)

#### 8️⃣ Post-Merge
- PR merged → Issue automatically closes (if you used `Closes #<number>`)
- Manually move issue to "Done" on the project board (or automate via GitHub Projects)
- Delete the feature branch

---

## 📋 Development Standards

### Code Quality Checklist
Before opening a PR, ensure:

```bash
# 1. Format code
vendor/bin/pint --dirty --format agent

# 2. Run static analysis
vendor/bin/phpstan analyse

# 3. Run tests
php artisan test --compact

# 4. Test migrations (up and down)
php artisan migrate:fresh --seed
php artisan migrate:rollback
```

### Testing Requirements
- **Every feature must have tests** (Pest 4)
- Tests should cover:
  - Model relationships and scopes
  - Policy authorization
  - Filament resource CRUD operations
  - Edge cases and validation rules

### Migration Standards
- Use descriptive migration names
- Always test `up()` and `down()` methods
- Include proper indexes and foreign keys
- Document complex schema changes

### Filament Resources
- Follow existing patterns in `app/Filament/`
- Use relation managers for nested resources
- Test CRUD operations manually in the browser before submitting PR
- Include appropriate permissions/policies

---

## 🤖 GitHub Actions Enforcement

### Commit Message Check
**Enforced format**: `US-<issue-number>: <description> #<issue-number>`

**Pass:**
```
US-1: Add ProtocolType enum #1
US-7: Create parameter extraction logic #7
US-1: Fix validation bug #1
```

**Fail:**
```
Add device types                    (missing US- prefix)
US-1 Add enum                       (missing colon)
Fixed bug                           (missing issue reference)
```

**Note:** The `#<issue-number>` at the end is recommended but not strictly enforced by hooks. It creates automatic GitHub issue linking.

### Branch Name Check
**Enforced format**: `feature/us-<number>-<slug>` or `hotfix/us-<number>-<slug>`

**Pass:**
```
feature/us-1-device-types
feature/us-7-parameter-definitions
hotfix/us-3-fix-validation
```

**Fail:**
```
feature/device-types               (missing us-<number>)
US-1-device-types                  (missing feature/ prefix)
feature/us-1_device_types          (underscores not allowed)
```

### PR Issue Link Check
PR title or description must reference an issue using:
- `US-<number>` in the title
- `#<number>` or `Closes #<number>` in the description

---

## 🎨 Code Style

This project uses:
- **Laravel Pint** for code formatting (PSR-12 with Laravel preset)
- **PHPStan** for static analysis (Level 8)
- **Rector** for automated refactoring

All enforced via CI pipeline.

---

## 🚀 Quick Reference

```bash
# Start new feature
git checkout -b feature/us-1-device-types

# Commit (follow format!)
git commit -m "US-1: Add DeviceType model"

# Push and open PR
git push origin feature/us-1-device-types

# Run quality checks locally
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
php artisan test --compact
```

---

## 💡 Tips

1. **Keep commits atomic** - One logical change per commit
2. **Write descriptive commit messages** - `US-1: Add ProtocolConfigCast` is better than `US-1: Update code`
3. **Test locally before pushing** - Run Pint, PHPStan, and tests
4. **Keep PRs focused** - One user story per PR
5. **Update the issue** - Move it through the project board as you progress

---

## ❓ Questions?

Check the project documentation:
- [Backlog & User Stories](plan/03-backlog.md)
- [ERD Documentation](plan/01-erd-core.md)
- [Agent Guidelines](AGENTS.md)

Or open a discussion on GitHub!
