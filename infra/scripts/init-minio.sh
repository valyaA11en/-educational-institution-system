#!/bin/bash

# Wait for MinIO to be ready
echo "Waiting for MinIO to be ready..."
MAX_RETRIES=30
RETRY_COUNT=0

while [ $RETRY_COUNT -lt $MAX_RETRIES ]; do
  if curl -f http://minio:9000/minio/health/live 2>/dev/null; then
    echo "MinIO is ready!"
    break
  fi
  echo "MinIO is not ready yet. Waiting... ($RETRY_COUNT/$MAX_RETRIES)"
  sleep 2
  RETRY_COUNT=$((RETRY_COUNT + 1))
done

if [ $RETRY_COUNT -eq $MAX_RETRIES ]; then
  echo "ERROR: MinIO did not become ready in time"
  exit 1
fi

# Set MinIO alias
mc alias set minio http://minio:9000 ${MINIO_ROOT_USER} ${MINIO_ROOT_PASSWORD} || {
  echo "ERROR: Failed to set MinIO alias"
  exit 1
}

# Create bucket if it doesn't exist
BUCKET_NAME=${AWS_BUCKET:-laravel}
echo "Creating bucket: ${BUCKET_NAME}"
mc mb minio/${BUCKET_NAME} --ignore-existing || true

# Create templates directory
echo "Creating templates directory..."
mc mb minio/${BUCKET_NAME}/templates --ignore-existing || true

# Upload placeholder template file if it exists
if [ -f "/tmp/order_default.docx" ]; then
  echo "Uploading order_default.docx template..."
  mc cp /tmp/order_default.docx minio/${BUCKET_NAME}/templates/order_default.docx || true
else
  echo "Template file not found, creating placeholder README..."
  cat > /tmp/templates_README.md << 'EOF'
# Document Templates

This directory contains DOCX templates for document generation.

## Uploading Templates

To upload a template, use MinIO Console (http://localhost:9001) or mc CLI:

```bash
mc cp your_template.docx minio/laravel/templates/your_template.docx
```

## Example Template: order_default.docx

This is a placeholder. Replace it with your actual template file.

### Template Variables

Templates use placeholders like `{variable_name}` that will be replaced with actual data.

Example:
- `{student_name}` - Student full name
- `{group_name}` - Group name
- `{date}` - Document date
EOF
  mc cp /tmp/templates_README.md minio/${BUCKET_NAME}/templates/README.md || true
fi

echo "MinIO initialization completed!"
