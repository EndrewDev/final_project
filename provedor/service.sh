#!/bin/bash

echo "Verificando se os containers estão rodando..."
docker ps -q --filter "name=proxy_container" --filter "name=dns_container" --filter "name=email" --filter "name=roundcube"

if [ $? -eq 0 ]; then
    echo "Containers estão em execução. Parando e removendo containers e imagens..."
    docker-compose down -v --rmi all
    docker system prune -af
else
    echo "Nenhum container em execução."
fi

echo "Construindo e subindo os containers..."
docker-compose up --build

echo "Containers e serviços estão rodando!"
