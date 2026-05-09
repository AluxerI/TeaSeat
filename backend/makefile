.PHONY: setup

setup:
	@echo "🚀 Начинаем настройку бэкенда..."
	@docker-compose exec backend bash -c "\
		echo '🧹 Очищаем кеш...' && \
		php artisan optimize:clear && \
		php artisan cache:clear && \
		php artisan config:clear && \
		php artisan route:clear && \
		php artisan view:clear && \
		php artisan filament:clear-cached-components && \
		\
		echo '🔗 Проверяем симлинк...' && \
		if [ -L public/storage ]; then \
			echo '✅ Симлинк уже существует'; \
		else \
			echo '📁 Создаем симлинк...' && \
			php artisan storage:link; \
		fi && \
		\
		echo '⚡ Кешируем...' && \
		php artisan config:cache && \
		php artisan route:cache && \
		php artisan view:cache && \
		php artisan filament:cache-components && \
		\
		echo '📚 Оптимизируем автозагрузку...' && \
		composer dump-autoload -o && \
		\
		echo '🔄 Перезапускаем Octane...' && \
		php artisan octane:reload && \
		\
		echo '✅ Готово!'"