# Build arguments
ARG ARCH="amd64"
ARG OS="linux"
ARG MYSQLD_EXPORTER_VERSION="0.17.2"  # specify a fixed version for reproducibility

# Base image (must come AFTER ARG so it can expand)
FROM quay.io/prometheus/busybox-${OS}-${ARCH}@sha256:f173c44fab35484fa0e940e42929efe2a2f506feda431ba72c5f0d79639d7f55

# re-declare args after FROM if you want them in this stage
ARG ARCH
ARG OS
ARG MYSQLD_EXPORTER_VERSION

LABEL maintainer="The Prometheus Authors <prometheus-developers@googlegroups.com>"

# Debug: print versions and variables
RUN echo "Building for OS=$OS ARCH=$ARCH VERSION=$MYSQLD_EXPORTER_VERSION"


# Install wget (busybox has a lightweight wget by default, but sometimes incomplete)
RUN wget --version || true

# RUN echo "https://github.com/prometheus/mysqld_exporter/releases/download/v${MYSQLD_EXPORTER_VERSION}/mysqld_exporter-${MYSQLD_EXPORTER_VERSION}.${OS}-${ARCH}.tar.gz" && exit 1


# Download precompiled mysqld_exporter binary
RUN mkdir -p /tmp/build && \
    wget -qO /tmp/build/mysqld_exporter.tar.gz \
      https://github.com/prometheus/mysqld_exporter/releases/download/v${MYSQLD_EXPORTER_VERSION}/mysqld_exporter-${MYSQLD_EXPORTER_VERSION}.${OS}-${ARCH}.tar.gz && \
    tar -xzf /tmp/build/mysqld_exporter.tar.gz -C /tmp/build && \
    mv /tmp/build/mysqld_exporter-${MYSQLD_EXPORTER_VERSION}.${OS}-${ARCH}/mysqld_exporter /bin/mysqld_exporter && \
    chmod +x /bin/mysqld_exporter && \
    rm -rf /tmp/build

# Create config directory with right ownership
USER root
RUN mkdir -p /etc/mysql && chown nobody /etc/mysql

# Copy startup script
COPY start.sh /start.sh
RUN chmod +x /start.sh

# Expose metrics port
EXPOSE 9104

# Run as unprivileged user
USER nobody

ENTRYPOINT [ "/start.sh" ]
