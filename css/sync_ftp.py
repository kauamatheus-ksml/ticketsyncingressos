import time
import ftplib
import os
from watchdog.observers import Observer
from watchdog.events import FileSystemEventHandler

# Configurações do FTP
FTP_HOST = "46.202.145.107"
FTP_USER = "u153409541.ticketsync.com.br"
FTP_PASS = "Aaku_2004@"
FTP_DIR = "/public_html"
LOCAL_DIR = "/mnt/c/Users/KAOS - PC/Documents/app/INGRESSOS"  # Ajuste conforme seu diretório local

class FTPUploader(FileSystemEventHandler):
    def on_modified(self, event):
        if not event.is_directory:
            self.upload(event.src_path)

    def on_created(self, event):
        if not event.is_directory:
            self.upload(event.src_path)

    def upload(self, file_path):
        file_name = os.path.basename(file_path)
        with ftplib.FTP(FTP_HOST, FTP_USER, FTP_PASS) as ftp:
            ftp.cwd(FTP_DIR)
            with open(file_path, "rb") as file:
                ftp.storbinary(f"STOR {file_name}", file)
        print(f"✅ Arquivo enviado: {file_name}")

if __name__ == "__main__":
    event_handler = FTPUploader()
    observer = Observer()
    observer.schedule(event_handler, LOCAL_DIR, recursive=True)
    observer.start()

    try:
        while True:
            time.sleep(1)
    except KeyboardInterrupt:
        observer.stop()
    observer.join()
