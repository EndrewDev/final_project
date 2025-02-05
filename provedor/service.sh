#!/bin/bash

start_containers() {
  echo "Iniciando containers..."
  docker-compose up --build
}

stop_containers() {
  echo "Parando e removendo containers e imagens..."
  docker-compose down --volumes --rmi all
}

if [ "$1" == "start" ]; then
  start_containers
elif [ "$1" == "stop" ]; then
  stop_containers
else
  echo "Uso correto: $0 {start|stop}"
  exit 1
fi
