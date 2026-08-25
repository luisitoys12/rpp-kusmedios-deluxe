# RPP Kusmedios Deluxe - Stream Platform Selector

WordPress plugin que extiende **Radio Player Page** con un selector de plataforma de streaming directamente en el editor de estaciones.

## Plataformas soportadas

- **Azuracast** - Station ID, Mount Point, API Key opcional
- **ZenoFM** - Station ID de ZenoFM
- **SonicPanel** - URL base + Puerto
- **Shoutcast v2** - URL base + SID
- **Icecast** - URL base + Mount Point
- **Manual / Otro** - URL directa

## Requisitos

- WordPress 6.6+
- PHP 7.4+
- Plugin **Radio Player Page** >= 3.4.x instalado y activo

## Instalacion

1. Subir la carpeta `rpp-kusmedios-deluxe` a `/wp-content/plugins/`
2. Activar desde **Plugins** en el panel de WordPress
3. Ir a **RPP > Stations**, crear o editar una estacion
4. En el metabox **Plataforma de Streaming - Kusmedios Deluxe**, seleccionar la plataforma
5. Rellenar los campos correspondientes y dar clic en **Probar conexion**
6. Dar clic en **Aplicar al Stream URL del player** para copiar la URL generada al campo RPP
7. Guardar la estacion

## REST API

Cada estacion expone un endpoint propio que actua como proxy al servidor de radio y normaliza la respuesta:

```
GET /wp-json/rpkus/v1/nowplaying/{station_post_id}
```

Respuesta normalizada (todos los formatos):
```json
{
  "artist": "Artista",
  "title": "Titulo de la cancion",
  "artwork": "https://...",
  "listeners": 42,
  "is_live": false,
  "dj_name": "DJ Kusmedios",
  "show_name": "El Matutino",
  "next_song": "Proximo tema"
}
```
> Los campos `dj_name`, `show_name` y `next_song` solo aplican para Azuracast.

La respuesta se cachea 14 segundos via WordPress Transients para no saturar el servidor de radio.

## Autor

Desarrollado para **estacionkusmedios.org** por Kusmedios Deluxe v1.0.0
