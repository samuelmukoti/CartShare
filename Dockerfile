# Build a custom WordPress image with the plugin baked in.
#
# Used by docker-compose.yml's `wordpress` service. Both local
# `docker compose up --build` and DevStation's hosted Live Preview
# service run this same Dockerfile.
#
# The .dockerignore in this directory excludes the build artefacts and
# .git so the image stays small.

FROM wordpress:latest

# Copy the plugin source into the wp-content/plugins/<slug>/ folder.
# The COPY trailing slash is important — without it docker would copy
# files into a file rather than into a directory.
COPY . /var/www/html/wp-content/plugins/cartshare/

# Make sure the plugin directory is owned by www-data (the user the
# php-apache image runs as) so WordPress can read it at runtime.
RUN chown -R www-data:www-data /var/www/html/wp-content/plugins/cartshare
