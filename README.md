# Drupal Observability Lab

A reproducible local lab for running a Drupal site with OpenTelemetry, an OpenTelemetry Collector, Grafana Tempo, Prometheus, Loki and Grafana.

The accompanying article explains the [observability concepts](https://medium.com/@sumitsaini7991/beyond-monitoring-understanding-modern-observability-52aa9d5c8ad6) and [implementation decisions](https://medium.com/@sumitsaini7991/i-instrumented-a-drupal-site-end-to-end-what-the-trace-actually-revealed-2c1a82bdc9d6). This README focuses only on **getting the lab running and verifying it**.

---

## Prerequisites

Install:

* Docker
* Docker Compose
* Git
---

## 1. Clone the repository

```bash
git clone https://github.com/1sumit-saini/drupal-observability-lab.git
```
```bash
cd drupal-observability-lab
```
---
### Repository structure

```text
.
├── docker-compose.yml
├── docker/
│   ├── alloy/
│   ├── drupal/
│   ├── grafana/
│   ├── loki/
│   ├── nginx/
│   ├── otel/
│   ├── prometheus/
│   └── tempo/
├── drupal/
│   ├── composer.json
│   ├── composer.lock
│   └── web/
│       └── modules/
│           └── custom/
│               └── observability_demo/
└── README.md
```

---

## 2. Start the containers

Build the Drupal image and start the complete stack:

```bash
docker compose up -d --build
```

Check the containers:

```bash
docker compose ps
```

You should see containers for:

* Drupal/PHP-FPM
* Nginx
* MySQL
* OpenTelemetry Collector
* Tempo
* Prometheus
* Loki
* Grafana
* Grafana Alloy

---

## 3. Install Drupal dependencies

Once the Drupal container is running, install the Composer dependencies:

```bash
docker compose exec drupal composer install
```

Verify that the OpenTelemetry PHP packages are installed:

```bash
docker compose exec drupal composer show open-telemetry/api
```

Verify the PHP OpenTelemetry extension:

```bash
docker compose exec drupal php -m | grep opentelemetry
```

You should see:

```text
opentelemetry
```

---

## 4. Install Drupal

Open:

```text
http://localhost:8080
```

Follow the Drupal installation wizard.

### Database settings

Use the following values:

| Setting                  | Value    |
| ------------------------ | -------- |
| Database type            | MySQL    |
| Database name            | `drupal` |
| Database username        | `drupal` |
| Database password        | `drupal` |
| Advanced → Database host | `mysql`  |
| Advanced → Port number   | `3306`   |

The important part is the **database host**.

Do **not** use:

```text
localhost
```

or:

```text
127.0.0.1
```

The Drupal container must connect to the MySQL container using the Docker Compose service name:

```text
mysql
```

Complete the Drupal installation.

---

## 5. Enable the Log Stdout module

The repository does not bundle the Drupal configuration, so enable `log_stdout` module after Drupal has been installed.

```bash
docker compose exec drupal vendor/bin/drush en log_stdout -y
```

Then open Drupal's **Configuration → Development → Logging and errors** and configure Log Stdout to write Drupal log messages to stdout.

The Log Stdout configuration is intentionally not committed to this repository, so this step must be completed after installing Drupal.

---

## 6. Verify the Drupal site

Open:

```text
http://localhost:8080
```

The demonstration route is:

```text
http://localhost:8080/observability-demo/node/{nid}
```

Where {nid} can be any existing node id, create one if not present. For example, 1

If necessary, create a basic node through the Drupal administration UI before opening the demonstration route.

---

## 7. Verify Grafana

Open:

```text
http://localhost:3000
```

The provisioned data sources should include:

* Tempo
* Prometheus
* Loki

The dashboard files are provisioned automatically when Grafana starts.

---

## 8. Verify Prometheus

Open:

```text
http://localhost:9090
```

Useful queries include:

```promql
drupal_calls_total
```

and:

```promql
drupal_duration_milliseconds_count
```

For example:

```promql
drupal_calls_total{span_name="Drupal controller"}
```

---

## 9. Verify Loki

Check that Loki is ready:

```bash
curl http://localhost:3100/ready
```

Expected response:

```text
ready
```

You can also check the Alloy container:

```bash
docker compose logs alloy
```

And Drupal container logs:

```bash
docker compose logs drupal
```

---

## 10. Generate telemetry

Open the demonstration route several times:

For example:

```text
http://localhost:8080/observability-demo/node/1
```

Then open Grafana:

```text
http://localhost:3000
```

Use the provisioned dashboard and the Tempo, Prometheus, and Loki data sources to inspect the generated telemetry.

---

## 11. Useful Docker commands

### Check container status

```bash
docker compose ps
```

### View all logs

```bash
docker compose logs
```

### Follow Drupal logs

```bash
docker compose logs -f drupal
```

### Follow OpenTelemetry Collector logs

```bash
docker compose logs -f otel-collector
```

### Follow Grafana Alloy logs

```bash
docker compose logs -f alloy
```

### Follow Tempo logs

```bash
docker compose logs -f tempo
```

### Restart the complete stack

```bash
docker compose restart
```

### Rebuild the Drupal image

```bash
docker compose up -d --build drupal
```

### Enter the Drupal container

```bash
docker compose exec drupal bash
```

### Enter the MySQL container

```bash
docker compose exec mysql bash
```

---

## 12. Access points

| Component  | URL                                             |
| ---------- | ----------------------------------------------- |
| Drupal     | http://localhost:8080                           |
| Demo route | http://localhost:8080/observability-demo/node/1 |
| Grafana    | http://localhost:3000                           |
| Prometheus | http://localhost:9090                           |
| Tempo      | http://localhost:3200                           |
| Loki       | http://localhost:3100                           |
| Alloy      | http://localhost:12345                          |

The OpenTelemetry Collector exposes:

```text
4317  OTLP/gRPC
4318  OTLP/HTTP
8888  Collector metrics
8889  Span metrics
```
