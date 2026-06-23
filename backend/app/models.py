import uuid
import secrets
import string
from datetime import datetime
from sqlalchemy import Column, Integer, String, Boolean, Enum, DECIMAL, Text, JSON, Date, ForeignKey, TIMESTAMP, UniqueConstraint
from sqlalchemy.orm import relationship
from app.database import Base


def generar_codigo():
    return ''.join(secrets.choice(string.ascii_uppercase + string.digits) for _ in range(6))


class Usuario(Base):
    __tablename__ = "usuarios"

    id = Column(Integer, primary_key=True, autoincrement=True)
    cedula = Column(String(20), unique=True, nullable=False)
    nombre = Column(String(100), nullable=False)
    email = Column(String(100), unique=True, nullable=True)
    password_hash = Column(String(255), nullable=False)
    rol = Column(Enum("admin", "docente"), nullable=False, default="docente")
    activo = Column(Boolean, nullable=False, default=False)
    created_at = Column(TIMESTAMP, default=datetime.utcnow)

    grupos = relationship("Grupo", back_populates="docente", cascade="all, delete-orphan")


class Grupo(Base):
    __tablename__ = "grupos"

    id = Column(Integer, primary_key=True, autoincrement=True)
    docente_id = Column(Integer, ForeignKey("usuarios.id", ondelete="CASCADE"), nullable=False)
    nombre = Column(String(50), nullable=False)
    codigo_acceso = Column(String(10), unique=True, nullable=False, default=generar_codigo)
    created_at = Column(TIMESTAMP, default=datetime.utcnow)

    docente = relationship("Usuario", back_populates="grupos")
    estudiantes = relationship("Estudiante", back_populates="grupo", cascade="all, delete-orphan")
    visibilidades = relationship("Visibilidad", back_populates="grupo", cascade="all, delete-orphan")


class Estudiante(Base):
    __tablename__ = "estudiantes"

    cedula = Column(String(20), primary_key=True)
    nombre = Column(String(100), nullable=False)
    grupo_id = Column(Integer, ForeignKey("grupos.id", ondelete="CASCADE"), nullable=False)
    created_at = Column(TIMESTAMP, default=datetime.utcnow)

    grupo = relationship("Grupo", back_populates="estudiantes")
    notas = relationship("Nota", back_populates="estudiante", cascade="all, delete-orphan")


class Curso(Base):
    __tablename__ = "cursos"

    id = Column(Integer, primary_key=True, autoincrement=True)
    nombre = Column(String(50), nullable=False)
    slug = Column(String(30), unique=True, nullable=False)
    icono = Column(String(10), default="📚")
    orden = Column(Integer, default=0)

    niveles = relationship("Nivel", back_populates="curso", cascade="all, delete-orphan")
    visibilidades = relationship("Visibilidad", back_populates="curso", cascade="all, delete-orphan")


class Nivel(Base):
    __tablename__ = "niveles"

    id = Column(Integer, primary_key=True, autoincrement=True)
    curso_id = Column(Integer, ForeignKey("cursos.id", ondelete="CASCADE"), nullable=False)
    numero = Column(Integer, nullable=False)
    titulo = Column(String(100), nullable=False)
    tipo = Column(Enum("leccion", "ejercicios", "evaluacion"), default="leccion")
    archivo_html = Column(String(200))
    orden = Column(Integer, default=0)

    curso = relationship("Curso", back_populates="niveles")
    notas = relationship("Nota", back_populates="nivel", cascade="all, delete-orphan")


class Visibilidad(Base):
    __tablename__ = "visibilidad"

    id = Column(Integer, primary_key=True, autoincrement=True)
    grupo_id = Column(Integer, ForeignKey("grupos.id", ondelete="CASCADE"), nullable=False)
    curso_id = Column(Integer, ForeignKey("cursos.id", ondelete="CASCADE"), nullable=False)
    mostrar_lecciones = Column(Boolean, nullable=False, default=True)
    mostrar_ejercicios = Column(Boolean, nullable=False, default=True)
    mostrar_evaluaciones = Column(Boolean, nullable=False, default=False)
    fecha_desde = Column(Date, nullable=True)
    fecha_hasta = Column(Date, nullable=True)

    grupo = relationship("Grupo", back_populates="visibilidades")
    curso = relationship("Curso", back_populates="visibilidades")

    __table_args__ = (UniqueConstraint("grupo_id", "curso_id", name="unique_vis"),)


class Nota(Base):
    __tablename__ = "notas"

    id = Column(Integer, primary_key=True, autoincrement=True)
    cedula_estudiante = Column(String(20), ForeignKey("estudiantes.cedula", ondelete="CASCADE"), nullable=False)
    nivel_id = Column(Integer, ForeignKey("niveles.id", ondelete="CASCADE"), nullable=False)
    nota = Column(DECIMAL(5, 2), nullable=False)
    tiempo_seg = Column(Integer, default=0)
    intento = Column(Integer, default=1)
    fecha = Column(TIMESTAMP, default=datetime.utcnow)

    estudiante = relationship("Estudiante", back_populates="notas")
    nivel = relationship("Nivel", back_populates="notas")


class ShareSession(Base):
    __tablename__ = "share_sessions"

    id = Column(String(36), primary_key=True, default=lambda: str(uuid.uuid4()))
    codigo_sala = Column(String(10), unique=True, nullable=False, default=generar_codigo)
    docente_id = Column(Integer, ForeignKey("usuarios.id", ondelete="CASCADE"), nullable=False)
    grupo_id = Column(Integer, nullable=True)
    contenido = Column(Text)
    lenguaje = Column(String(20), default="python")
    cursor_pos = Column(JSON)
    is_active = Column(Boolean, nullable=False, default=True)
    created_at = Column(TIMESTAMP, default=datetime.utcnow)


class LogActividad(Base):
    __tablename__ = "logs_actividad"

    id = Column(Integer, primary_key=True, autoincrement=True)
    cedula_estudiante = Column(String(20))
    evento = Column(String(50))
    nivel_id = Column(Integer, nullable=True)
    created_at = Column(TIMESTAMP, default=datetime.utcnow)
