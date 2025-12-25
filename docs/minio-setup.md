# MinIO Setup and Configuration

## Overview

MinIO is used as S3-compatible storage for file uploads, document templates, and generated documents.

## Initialization

The MinIO bucket and required directories are automatically initialized when you start the Docker Compose stack via the `minio-init` service.

### Automatic Initialization

The `minio-init` service:
1. Waits for MinIO to be ready
2. Creates the bucket specified in `AWS_BUCKET` (default: `laravel`)
3. Creates the `templates/` directory inside the bucket
4. Uploads a placeholder README if no template file is provided

### Manual Initialization

If you need to manually initialize MinIO:

```bash
# Install MinIO Client (mc)
# On macOS: brew install minio/stable/mc
# On Linux: wget https://dl.min.io/client/mc/release/linux-amd64/mc && chmod +x mc

# Set alias
mc alias set minio http://localhost:9000 minioadmin minioadmin

# Create bucket
mc mb minio/laravel

# Create templates directory
mc mb minio/laravel/templates
```

## Uploading Document Templates

### Using MinIO Console

1. Open MinIO Console: http://localhost:9001
2. Login with credentials from `.env`:
   - Username: `MINIO_ROOT_USER` (default: `minioadmin`)
   - Password: `MINIO_ROOT_PASSWORD` (default: `minioadmin`)
3. Navigate to your bucket → `templates/` folder
4. Click "Upload" and select your `.docx` template file

### Using MinIO Client (mc)

```bash
# Set alias (if not already set)
mc alias set minio http://localhost:9000 minioadmin minioadmin

# Upload template
mc cp /path/to/your_template.docx minio/laravel/templates/order_default.docx
```

### Using Laravel Storage

```php
use Illuminate\Support\Facades\Storage;

Storage::disk('s3')->put('templates/order_default.docx', file_get_contents('/path/to/template.docx'));
```

## Template File Structure

Templates should be `.docx` files created with Microsoft Word or LibreOffice Writer.

### Placeholder Format

Use placeholders in the format `{variable_name}` that will be replaced during document generation:

Example:
```
Order #{order_number}
Date: {date}
Student: {student_name}
Group: {group_name}
```

### Available Template Variables

Template variables are defined in the `data_json` field when creating a document. Common variables include:

- `{student_name}` - Student full name
- `{group_name}` - Group name
- `{date}` - Document date
- `{order_number}` - Order number
- Custom variables as needed

## Environment Variables

Required S3/MinIO configuration in `.env`:

```env
# MinIO / S3
AWS_ACCESS_KEY_ID=minioadmin
AWS_SECRET_ACCESS_KEY=minioadmin
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=laravel
AWS_ENDPOINT=http://minio:9000
AWS_USE_PATH_STYLE_ENDPOINT=true
FILESYSTEM_DISK=s3

# S3 Bucket Configuration
S3_BUCKET=laravel
S3_TEMPLATES_PATH=templates
```

## Access URLs

- **MinIO API**: http://localhost:9000
- **MinIO Console**: http://localhost:9001
- **Credentials**: See `MINIO_ROOT_USER` and `MINIO_ROOT_PASSWORD` in `.env`

## Troubleshooting

### Bucket Not Created

If the bucket is not created automatically:

1. Check that `minio-init` service ran successfully:
   ```bash
   docker compose logs minio-init
   ```

2. Manually create the bucket (see Manual Initialization above)

### Template Not Found

If templates are not found:

1. Verify the file exists in MinIO Console
2. Check the path: `{bucket_name}/templates/{template_name}.docx`
3. Ensure the `doc_templates` table has the correct `file_template_key` pointing to the S3 path

### Permission Issues

If you encounter permission issues:

1. Verify `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY` match MinIO credentials
2. Check bucket policy in MinIO Console
3. Ensure the bucket exists and is accessible

