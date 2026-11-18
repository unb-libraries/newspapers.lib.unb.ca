# vips for DZI tiling (vips dzsave). Published to ghcr by .github/workflows/nbnp-ingest.yaml;
# reference it by digest in the nbnp-ingest config.
# The base is pinned by digest; bump it deliberately.
FROM debian:bookworm-slim@sha256:734a72b3d6142a71993b4bc580ca01d8ca687f77a2c422d96a961d3376b179bc

RUN apt-get update \
  && apt-get install -y --no-install-recommends libvips-tools \
  && rm -rf /var/lib/apt/lists/*

LABEL org.opencontainers.image.source="https://github.com/unb-libraries/newspapers.lib.unb.ca" \
      org.opencontainers.image.description="vips for nbnp-ingest DZI tiling"
