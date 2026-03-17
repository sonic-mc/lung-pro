## Deep Learning–Based Lung Cancer Detection Platform

Hybrid scaffold that integrates:
- Laravel web application for patient workflows and prediction dashboard
- Python FastAPI AI service for segmentation + classification inference

### Architecture

- Laravel responsibilities:
	- User roles scaffold (`admin`, `radiologist`, `researcher`)
	- Patient records, scan uploads, prediction persistence
	- Dashboard and report views
	- REST communication to Python `/predict`
- Python responsibilities:
	- Image preprocessing and augmentation
	- U-Net segmentation + CNN classification hybrid model
	- Grad-CAM-like heatmap generation
	- FastAPI endpoint serving predictions

### Laravel Flow

1. Upload image from `/scans/upload`
2. Store image in `storage/app/images` via `medical_images` disk
3. Send image multipart request to Python AI service
4. Receive prediction payload
5. Save prediction in database and show dashboard/report

### Clinical Visual Output Stage

The prediction details page presents clinician feedback in a three-panel layout:

- Left: Original scan
- Center: AI-highlighted scan (heatmap ON/OFF with optional segmentation boundary)
- Right: Diagnostic results (prediction, probability, severity score, confidence band, finding location)

End-to-end sequence:

Upload Scan → Preprocess → Segment → Classify → Generate Heatmap → Visualize Affected Areas → Severity & Confidence → Report → Radiologist Review

### Core Database Tables

- `users` (with `role`)
- `patients`
- `scans`
- `predictions`

### Python API Contract

`POST /predict`

Example response:

```json
{
	"prediction": "Benign",
	"probability": 0.92,
	"heatmap": "gradcam_image.png"
}
```

### Run (Laravel)

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

Set AI service URL in `.env`:

```dotenv
AI_SERVICE_BASE_URL=http://127.0.0.1:8001
AI_SERVICE_TIMEOUT=60
```

### Run (Python AI Service)

```bash
cd ai-service
python -m venv .venv
.venv\Scripts\activate
pip install -r requirements.txt
uvicorn api.main:app --host 0.0.0.0 --port 8001 --reload
```

### Deploy Now (Docker Compose)

This repository includes a production deployment stack in `docker-compose.prod.yml` with:
- `app` (Laravel web)
- `queue` (Laravel queue worker)
- `ai-service` (FastAPI inference)
- `mysql` (MySQL 8.4)

1) Create your env file and set production values:

```bash
cp .env.example .env
```

Required edits in `.env` before first start:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=http://YOUR_SERVER_IP_OR_DOMAIN
APP_KEY=base64:GENERATE_AND_PASTE_A_REAL_KEY

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=lung
DB_USERNAME=lung_user
DB_PASSWORD=change_me

AI_SERVICE_BASE_URL=http://ai-service:8001
AI_SERVICE_TIMEOUT=60
```

Generate an app key (copy output into `APP_KEY`):

```bash
docker compose -f docker-compose.prod.yml run --rm app php artisan key:generate --show
```

2) Build and start all containers:

```bash
docker compose -f docker-compose.prod.yml up -d --build
```

3) Follow startup logs:

```bash
docker compose -f docker-compose.prod.yml logs -f app ai-service queue
```

4) Access services:
- Laravel app: `http://SERVER_IP:8080`
- AI health: `http://SERVER_IP:8001/health`

5) Stop stack:

```bash
docker compose -f docker-compose.prod.yml down
```

### Scaffold Notes

- `training/train.py` and `training/evaluate.py` are starter pipelines for experimentation.
- `inference/predict.py` supports checkpoint loading from `ai-service/artifacts/checkpoints/hybrid_model.pt`.
- This scaffold is structured for academic research evolution and production hardening.

---

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
