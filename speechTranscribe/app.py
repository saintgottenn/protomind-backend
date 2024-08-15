from fastapi import FastAPI
import os
from pydantic import BaseModel
from transcribe import recognize_large_file

MAX_NUM_THREADS = 4

class FileRequest(BaseModel):
    filepath: str

app = FastAPI()

@app.post("/transcribe/")
async def transcribe(request: FileRequest):
    if not os.path.exists(request.filepath):
        return {"error": "File not found"}

    text, text_with_time = recognize_large_file(request.filepath, num_threads=MAX_NUM_THREADS)
    return {'text': text, 'text_with_time': text_with_time}
