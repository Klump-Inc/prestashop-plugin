start up:
	clear && docker compose up -d

#Show dashboard logs
start_with_logs:
	clear && docker compose up -d && docker compose logs -f