# LMU IoT Portal

> Multi-tenant IoT device management platform for monitoring and controlling connected devices

## 🚀 Quick Start

### Prerequisites

- **PHP 8.4+** (via [Laravel Herd](https://herd.laravel.com/) recommended)
- **Composer** 2.x
- **Node.js** 20+ & **NPM**
- **PostgreSQL** 16+ with the **TimescaleDB** extension
- **Redis** (for queues/cache via Horizon)
- **Docker** (for the NATS broker)

### 1. Clone & Install Dependencies

```bash
git clone https://github.com/tharindarodrigo/lmu-iot-portal.git
cd lmu-iot-portal

composer install
npm install
```

### 2. Environment Configuration

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` and set your database credentials:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=lmu_iot_portal
DB_USERNAME=your_user
DB_PASSWORD=your_password
```

### 3. PostgreSQL & TimescaleDB Setup

The project uses [TimescaleDB](https://www.timescale.com/) for time-series telemetry data. Install the extension on your PostgreSQL instance:

```bash
# macOS (Homebrew)
brew install timescaledb

# Then enable it in your postgresql.conf
timescaledb-tune --quiet --yes

# Restart PostgreSQL
brew services restart postgresql@16
```

Verify TimescaleDB is available:

```sql
-- Connect to your database
psql -d lmu_iot_portal

-- Check the extension loads
CREATE EXTENSION IF NOT EXISTS timescaledb;
SELECT extversion FROM pg_extension WHERE extname = 'timescaledb';
```

> The migration `2026_02_06_182310_create_device_telemetry_logs_table` automatically enables the extension and creates a hypertable on `device_telemetry_logs.recorded_at` when running on PostgreSQL.

### 4. Database Migration & Seeding

```bash
php artisan migrate --seed
```

This creates all tables, enables TimescaleDB hypertables, syncs permissions, and seeds demo data including:
- Admin user (`admin@admin.com` / password)
- Organizations with devices
- Device types (thermal sensor, smart fan, dimmable light)
- Schema versions with parameter definitions

### 5. Build Frontend Assets

```bash
npm run build

# OR for development with hot reload:
npm run dev
```

### 6. Start the Platform Services (Recommended)

Use the unified runner to start all required local services:

```bash
./scripts/platform-up.sh

# Optional: include Vite for hot reload
./scripts/platform-up.sh --vite
```

Composer aliases are also available:

```bash
composer platform:up
composer platform:up:vite
```

`platform-up` starts:
- NATS broker (Docker)
- Laravel Reverb (`php artisan reverb:start --port=8090`)
- Device state listener (`php artisan iot:listen-for-device-states`)
- Device presence listener (`php artisan iot:listen-for-device-presence`)
- Telemetry ingestion listener (`php artisan iot:ingest-telemetry`)
- Horizon (`php artisan horizon`)
- Scheduler worker (`php artisan schedule:work`)
- Vite (`npm run dev`) only when `--vite` is passed

To stop everything:

```bash
./scripts/platform-down.sh

# Or:
composer platform:down
```

### 7. Start Services Manually (Alternative)

If you prefer to run services individually:

```bash
docker compose -f docker-compose.nats.yml up -d
php artisan reverb:start --port=8090
php artisan iot:listen-for-device-states
php artisan iot:listen-for-device-presence
php artisan iot:ingest-telemetry
php artisan horizon
php artisan schedule:work
```

Optional frontend hot reload:

```bash
npm run dev
```

The NATS configuration is at `docker/nats/nats.conf` and includes:
- JetStream enabled with 256 MB memory / 2 GB file storage
- MQTT support on port 1883
- HTTP monitoring on port 8222 (mapped to host 8223)

### 8. Configure Git Workflow (Contributors)

```bash
./scripts/setup-dev.sh
```

### Access the Application

If using Laravel Herd:
```
https://lmu-iot-portal.test
```

Otherwise:
```bash
php artisan serve
```

Default admin login: `admin@admin.com` / `password`

---

## 🔧 Running Services Summary

For full functionality, you need these services running:

| Service | Command | Purpose |
|---------|---------|---------|
| Unified Startup (recommended) | `./scripts/platform-up.sh` | Starts full local stack in one command |
| Unified Startup + Vite | `./scripts/platform-up.sh --vite` | Full stack + frontend hot reload |
| Unified Shutdown | `./scripts/platform-down.sh` | Stops managed processes + NATS container |
| NATS Broker | `docker compose -f docker-compose.nats.yml up -d` | Message broker (MQTT + NATS) |
| Laravel Reverb | `php artisan reverb:start --port=8090` | WebSocket server for real-time UI |
| Device State Listener | `php artisan iot:listen-for-device-states` | Bridges device state to dashboard |
| Device Presence Listener | `php artisan iot:listen-for-device-presence` | Tracks online/offline via presence topics |
| Telemetry Ingestion Listener | `php artisan iot:ingest-telemetry` | Subscribes to inbound telemetry and queues processing |
| Horizon | `php artisan horizon` | Queue worker (default + ingestion + simulations) |
| Scheduler Worker | `php artisan schedule:work` | Runs scheduled maintenance jobs continuously |
| Vite (dev only) | `npm run dev` | Frontend hot reload |

---

## 📡 Device Control & Simulation

### Mock Device (Software Simulator)

Simulate a device that subscribes to command topics and responds with state:

```bash
php artisan iot:mock-device
# Interactive — search and select a device, then it listens for commands
```

### Manual State Publish

Publish a state update as if a device sent it:

```bash
php artisan iot:manual-publish
# Interactive — select device, topic, fill parameters, publish to NATS
```

### Physical Device (ESP32)

The project includes ESP32-S3 firmware for a dimmable light demo device at `plan/DeviceControlArchitecture/esp32-dimmable-light/`.

The ESP32 connects via MQTT to the NATS broker on port `1883` and uses topics:
- **Subscribe** (commands): `devices/dimmable-light/{device_id}/control`
- **Publish** (state): `devices/dimmable-light/{device_id}/state`

---

## 🧪 Development

### Running Tests

```bash
# Run all tests
php artisan test --compact

# Run specific test file
php artisan test --compact tests/Feature/DeviceTypeTest.php

# Run with filter
php artisan test --compact --filter=DeviceCommandDispatcher

# Run with coverage
php artisan test --coverage
```

### Code Quality

```bash
# Format code (Laravel Pint)
vendor/bin/pint --dirty --format agent

# Static analysis (PHPStan)
vendor/bin/phpstan analyse

# Run all quality checks (format + analyse)
composer run x      # with auto-fix
composer run x-test # dry-run (CI mode)
```

## 🔄 Contributing

This project follows a structured git-flow workflow with automated enforcement.

### Quick Workflow

1. **Start a new feature**
   ```bash
   ./scripts/new-feature.sh
   # Follow the prompts to create your feature branch
   ```

2. **Make your changes**
   ```bash
   # Commits are automatically validated
   git commit -m "US-1: Add ProtocolType enum #1"
   ```

3. **Push and create PR**
   ```bash
   git push origin feature/us-1-device-types
   # Then open a PR on GitHub
   ```

4. **Automated checks**
   - ✅ Commit message format (`US-<number>:` prefix)
   - ✅ Branch naming (`feature/us-<number>-<slug>`)
   - ✅ Issue linking in PR
   - ✅ Tests, Pint, PHPStan

📚 **Full workflow details**: See [CONTRIBUTING.md](CONTRIBUTING.md)

## 📂 Project Structure

```
app/
├── Console/Commands/IoT/  # Artisan commands (listener, mock device, manual publish)
├── Domain/                # Domain logic (DDD bounded contexts)
│   ├── DeviceControl/     # Command dispatching, command logs
│   ├── DeviceManagement/  # Devices, device types, NATS publishing
│   ├── DeviceSchema/      # Schemas, versions, parameters, topics
│   └── Shared/            # Users, organizations
├── Events/                # Broadcast events (CommandDispatched, DeviceStateReceived)
├── Filament/              # Admin panel resources
│   ├── Admin/             # Super admin panel (cross-tenant)
│   └── Portal/            # Organization portal (tenant-aware)
├── Http/                  # Controllers, middleware
└── Policies/              # Authorization policies

database/
├── migrations/            # Schema (includes TimescaleDB hypertables)
├── factories/             # Model factories
└── seeders/               # Demo data seeders

docker/
└── nats/nats.conf         # NATS broker configuration

plan/
├── DeviceControlArchitecture/  # Architecture docs + ESP32 firmware
├── 01-erd-core.md              # Core ERD
├── 02-erd-extension.md         # Extended ERD
└── 03-backlog.md               # User stories backlog

tests/
├── Feature/               # Feature tests
├── Filament/              # Filament-specific tests
└── Unit/                  # Unit tests
```

## 📊 Features

### Phase 1 - Core Schema & Admin UI
- ✅ Multi-tenant organization management
- ✅ Role-based access control (enum-permission)
- ✅ Device type catalog with protocol configs
- ✅ Schema versioning with parameter definitions
- ✅ Device registration and provisioning
- 🔄 Latest readings snapshot

### Phase 2 - Advanced Admin Features
- 📋 Rich schema version editor
- 📋 Guided device provisioning wizard
- 📋 Bulk operations

### Phase 3 - Telemetry Ingestion
- ✅ MQTT/NATS ingestion pipeline (via NATS MQTT bridge)
- ✅ Parameter validation & transformation
- ✅ TimescaleDB hypertable for telemetry logs
- 📋 Derived parameter calculation

### Phase 4 - Dashboards & Visualization
- ✅ Real-time device control dashboard (WebSocket via Reverb)
- ✅ Live message flow visualization
- 📋 Time-series charts
- 📋 Alerts and notifications

### Phase 5 - Device Control
- ✅ Command sending via NATS (downlink)
- ✅ Real-time command lifecycle tracking (dispatched → sent → acknowledged)
- ✅ Device state reception and broadcasting
- ✅ NATS KV store for last known device state

### Phase 6 - Simulation & Evaluation
- ✅ Mock device CLI simulator
- ✅ Manual device publish command
- ✅ ESP32 dimmable light firmware
- 📋 Performance testing
- 📋 Demo data generation

## 🛠 Tech Stack

- **Backend**: Laravel 12, PHP 8.4
- **UI**: Filament 5, Livewire 4, Alpine.js, Tailwind CSS
- **Database**: PostgreSQL 16+ with TimescaleDB (JSONB for flexible schemas, hypertables for telemetry)
- **Messaging**: NATS 2.10 (JetStream + MQTT bridge) via Docker
- **Real-time**: Laravel Reverb (WebSockets), Pusher JS client
- **Queue**: Laravel Horizon (Redis)
- **Testing**: Pest 4
- **Code Quality**: Pint, PHPStan (Level 8), Rector
- **IoT Protocols**: MQTT (via NATS MQTT bridge), NATS (native)

## 📖 Documentation

- [Contributing Guide](CONTRIBUTING.md) - Git workflow, code standards
- [Agent Guidelines](AGENTS.md) - AI coding assistant guidelines
- [Device Control Architecture](plan/DeviceControlArchitecture/01-device-control-flow.md) - Command/state flow design
- [Project Planning](plan/) - ERD, backlog, technical decisions

## 🔐 Security

If you discover a security vulnerability, please email security@example.com. All security vulnerabilities will be promptly addressed.

## 📄 License

This project is licensed under the MIT License.

## 🙏 Acknowledgments

- Laravel & Filament communities
- LMU faculty and contributors

---

**Current Status**: Phase 5 Development — Device Control (see [Backlog](plan/03-backlog.md))
