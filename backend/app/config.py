import os
from dotenv import load_dotenv

load_dotenv()

MYSQL_HOST = os.getenv("MYSQL_HOST", "mariadb")
MYSQL_PORT = int(os.getenv("MYSQL_PORT", "3306"))
MYSQL_USER = os.getenv("MYSQL_USER", "app_user")
MYSQL_PASSWORD = os.getenv("MYSQL_PASSWORD", "app_pass_2026")
MYSQL_DATABASE = os.getenv("MYSQL_DATABASE", "materiales1")

JWT_SECRET = os.getenv("JWT_SECRET", "super_secret_key_change_in_production_2026")
JWT_ALGORITHM = "HS256"
JWT_EXPIRE_HOURS = 24

ADMIN_KEY = os.getenv("ADMIN_KEY", "admin_key_for_lti_2026")
ADMIN_DEFAULT_USER = os.getenv("ADMIN_DEFAULT_USER", "admin")
ADMIN_DEFAULT_PASSWORD = os.getenv("ADMIN_DEFAULT_PASSWORD", "Admin2026!")

PISTON_API_URL = os.getenv("PISTON_API_URL", "https://emkc.org/api/v2/piston")

DATABASE_URL = os.getenv(
    "DATABASE_URL",
    f"mysql+aiomysql://{MYSQL_USER}:{MYSQL_PASSWORD}@{MYSQL_HOST}:{MYSQL_PORT}/{MYSQL_DATABASE}"
)
DATABASE_URL_SYNC = os.getenv(
    "DATABASE_URL_SYNC",
    f"mysql+pymysql://{MYSQL_USER}:{MYSQL_PASSWORD}@{MYSQL_HOST}:{MYSQL_PORT}/{MYSQL_DATABASE}"
)
