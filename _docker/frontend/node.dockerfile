FROM node:latest as builder

# Устанавливаем рабочую директорию
WORKDIR /app

# Копируем package.json и package-lock.json
COPY ./frontend/package*.json ./

# Устанавливаем зависимости
RUN npm install

# Копируем все файлы проекта
COPY ./frontend .

# Копируем соответствующий .env файл
ARG NODE_ENV
COPY ./frontend/.env.${NODE_ENV} ./.env

# Проверяем содержимое директории (для отладки)
RUN ls -la

# Собираем приложение
RUN npm run build

# Второй этап - используем nginx для раздачи статики
FROM nginx:alpine

# Копируем собранное приложение из первого этапа
COPY --from=builder /app/dist /usr/share/nginx/html

# Копируем конфиг nginx
COPY ./_docker/frontend/nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 3008

CMD ["nginx", "-g", "daemon off;"]
