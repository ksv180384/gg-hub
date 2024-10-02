FROM node:20.17

WORKDIR /var/www/socket_server

# Для смены типа переноса строк
#RUN apt-get update && apt-get install -y dos2unix

COPY ./socket_server/package*.json /var/www/socket_server
# Устанавливаем зависимости
RUN npm install

COPY ./socket_server /var/www/socket_server
#COPY ./_docker/nodejs /var/www/_docker/nodejs

ENV FASTIFY_ADDRESS=0.0.0.0

# Компилируем TypeScript в JavaScript
#RUN npm run dev

# Открываем порт, на котором будет работать сервер
EXPOSE 3007

# Меняем тип переноса строк на LF
#RUN dos2unix /var/www/_docker/nodejs/entrypoint.sh && chmod +x /var/www/_docker/nodejs/entrypoint.sh


