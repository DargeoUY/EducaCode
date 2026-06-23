from typing import Dict, Set
from fastapi import WebSocket


class ConnectionManager:
    def __init__(self):
        self.active_connections: Dict[str, Set[WebSocket]] = {}
        self.room_data: Dict[str, dict] = {}

    async def connect(self, websocket: WebSocket, room: str, rol: str):
        await websocket.accept()
        if room not in self.active_connections:
            self.active_connections[room] = set()
        self.active_connections[room].add(websocket)

        if rol == "docente":
            if room in self.room_data:
                await websocket.send_json({"type": "restore", "content": self.room_data[room].get("content", ""), "language": self.room_data[room].get("language", "python")})

        for ws in self.active_connections[room]:
            if ws != websocket:
                try:
                    await ws.send_json({"type": "user_joined", "rol": rol})
                except Exception:
                    pass

    async def disconnect(self, websocket: WebSocket, room: str):
        if room in self.active_connections:
            self.active_connections[room].discard(websocket)
            if not self.active_connections[room]:
                del self.active_connections[room]

    async def broadcast(self, room: str, data: dict, sender: WebSocket):
        if room in self.active_connections:
            dead = set()
            for ws in self.active_connections[room]:
                if ws != sender:
                    try:
                        await ws.send_json(data)
                    except Exception:
                        dead.add(ws)
            self.active_connections[room] -= dead

    async def broadcast_all(self, room: str, data: dict):
        if room in self.active_connections:
            dead = set()
            for ws in self.active_connections[room]:
                try:
                    await ws.send_json(data)
                except Exception:
                    dead.add(ws)
            self.active_connections[room] -= dead

    def save_content(self, room: str, content: str, language: str = "python"):
        self.room_data[room] = {"content": content, "language": language}

    def get_connections_count(self, room: str) -> int:
        return len(self.active_connections.get(room, set()))


manager = ConnectionManager()
