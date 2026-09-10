# node:22-alpine ships npm 10.9.4, whose arborist crashes ("Cannot read
# properties of null (reading 'edgesOut')") on the symfony/ux file: deps.
# npm >= 11 fixes it.
FROM node:22-alpine
RUN npm install -g npm@11
WORKDIR /app
