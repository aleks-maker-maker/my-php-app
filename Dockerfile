FROM php:8.2-cli-bookworm AS builder

WORKDIR /app
COPY src/ ./src/
RUN find src -type f -name '*.php' -print0 | xargs -0 -r -n 1 php -l

FROM php:8.2-cli-bookworm

RUN useradd --create-home appuser
WORKDIR /home/appuser
COPY --from=builder /app/src/ ./app/

USER appuser
CMD ["php", "./app/index.php"]
