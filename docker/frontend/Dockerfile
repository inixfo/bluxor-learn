FROM node:22-alpine AS build

WORKDIR /app

COPY design/project/package.json design/project/package-lock.json ./
RUN npm ci

COPY design/project ./
RUN npm run build

FROM nginx:1.27-alpine AS runtime

COPY docker/nginx/main.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html

RUN mkdir -p /var/www/storage-public
