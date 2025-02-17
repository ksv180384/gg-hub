# base image
FROM node:latest

# set working directory
WORKDIR /gg

# add `/app/node_modules/.bin` to $PATH
ENV PATH /gg/node_modules/.bin:$PATH

# install and cache app dependencies
COPY ./frontend/package.json /gg/package.json
RUN npm install

COPY ./frontend ./imagines

# start app
CMD ["npm", "run", "dev"]
