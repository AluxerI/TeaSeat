# syntax=docker/dockerfile:1.7

FROM node:22-alpine AS build

WORKDIR /app/frontend

COPY frontend/package.json frontend/package-lock.json ./

RUN --mount=type=cache,target=/root/.npm \
    npm ci --legacy-peer-deps --no-audit --no-fund

COPY frontend/ ./

ARG VITE_API_URL=""
ARG VITE_ADMIN_URL=/admin

ENV VITE_API_URL=${VITE_API_URL} \
    VITE_ADMIN_URL=${VITE_ADMIN_URL}

RUN npm run typecheck \
    && npm run build

FROM nginx:1.27-alpine AS production

COPY deploy/frontend/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/frontend/dist /usr/share/nginx/html

EXPOSE 8080
