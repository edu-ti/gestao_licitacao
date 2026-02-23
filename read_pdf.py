import PyPDF2

pdf_path = "MODELO DE PROPOSTA.pdf"
out_path = "modelo_proposta_ext.txt"
try:
    with open(pdf_path, "rb") as file:
        reader = PyPDF2.PdfReader(file)
        text = ""
        for page in reader.pages:
            text += page.extract_text()
            
    with open(out_path, "w", encoding="utf-8") as out_file:
        out_file.write(text)
        
    print("Success: text extracted to", out_path)
except Exception as e:
    print("Error:", e)
