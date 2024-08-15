from fastapi import FastAPI
import os
from pydantic import BaseModel
from transcribe import recognize_file


class FileRequest(BaseModel):
    filepath: str

app = FastAPI()

@app.post("/transcribe/")
async def transcribe(request: FileRequest):
    if not os.path.exists(request.filepath):
        return {"error": "File not found"}

    text, text_with_time = recognize_file(request.filepath)
    return {'text': text, 'text_with_time': text_with_time}
