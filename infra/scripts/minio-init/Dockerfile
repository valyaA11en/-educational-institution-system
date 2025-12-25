FROM minio/mc:latest

RUN apk add --no-cache bash curl

WORKDIR /scripts
COPY init-minio.sh /scripts/init-minio.sh
RUN chmod +x /scripts/init-minio.sh

CMD ["/scripts/init-minio.sh"]
