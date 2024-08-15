import wave
import json
from fastapi import FastAPI
from vosk import Model, KaldiRecognizer
from pydantic import BaseModel
from concurrent.futures import ThreadPoolExecutor, as_completed


app = FastAPI()
model = Model(model_path='./model')

class FileRequest(BaseModel):
    filepath: str

def recognize_file(file_path):
    with wave.open(file_path, "rb") as wf:
        rec = KaldiRecognizer(model, wf.getframerate())
        rec.SetWords(True)

        full_text = []
        text_with_time = []
        while True:
            data = wf.readframes(4000)  # Читаем небольшими порциями
            if len(data) == 0:
                break
            if rec.AcceptWaveform(data):
                result = json.loads(rec.Result())
                full_text.append(result.get("text", ""))
                text_with_time.append(result.get('result', ''))

    return " ".join(full_text).strip(), text_with_time

def _process_chunk(chunk, sample_rate):
    rec = KaldiRecognizer(model, sample_rate)
    rec.SetWords(True)
    rec.AcceptWaveform(chunk)
    result = json.loads(rec.Result())
    return result

def recognize_large_file(file_path, num_threads=4):
    with wave.open(file_path, "rb") as wf:
        sample_rate = wf.getframerate()
        total_samples = wf.getnframes()
        chunk_duration = 10  # seconds
        chunk_size = int(sample_rate * chunk_duration)

        chunks = []
        for i in range(0, total_samples, chunk_size):
            wf.setpos(i)
            chunk = wf.readframes(chunk_size)
            chunks.append(chunk)

    full_text = ""
    full_text_with_time = []

    with ThreadPoolExecutor(max_workers=num_threads) as executor:
        future_to_chunk = {executor.submit(_process_chunk, chunk, sample_rate): chunk for chunk in chunks}
        for future in as_completed(future_to_chunk):
            rec_result = future.result()
            raw_text = rec_result.get("text", "")
            text_with_time = rec_result.get('result', [])
            full_text += raw_text + " "
            full_text_with_time.extend(text_with_time)

    return full_text.strip(), full_text_with_time
