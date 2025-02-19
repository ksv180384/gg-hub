FROM node:latest as builder

WORKDIR /app

# Копируем package.json и package-lock.json
COPY ./frontend/package*.json ./

# Устанавливаем зависимости
RUN npm install

# Копируем все файлы проекта
COPY ./frontend .

# Используем переменную окружения
ENV NODE_ENV=production

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
