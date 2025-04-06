# base image
FROM node:22-alpine

# set working directory
WORKDIR /gg

# add `/app/node_modules/.bin` to $PATH
ENV PATH /gg/node_modules/.bin:$PATH

# install and cache app dependencies
COPY ./frontend/package.json /gg/package.json
RUN npm install

COPY ./frontend ./gg

# Добавляем глобальную установку serve
RUN npm install -g serve

# Изменяем команду запуска
CMD if [ "$NODE_ENV" = "production" ]; then \
      npm run build && serve -s dist -l 3008; \
    else \
      npm run dev; \
    fi
