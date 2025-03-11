start up:
	clear && docker compose up -d

#Show dashboard logs
sl start_with_logs:
	clear && docker compose up -d && docker compose logs -f

#Rebuild dashboard environment
rebuild:
	clear && docker compose down --remove-orphans  && docker compose rm && docker compose up --build -d --force-recreate && docker compose logs -f

ssh_prestashop:
	docker exec -it klump_prestashop bash

#stop dashboard
stop down:
	clear && docker compose down --remove-orphans && docker compose rm