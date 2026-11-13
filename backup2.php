<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ข้อมูลยานยนต์ (Backup)</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --bg-color: #f1f5f9;
            --card-bg: #ffffff;
            --text-main: #334155;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
        }

        body {
            font-family: 'Prompt', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            margin: 0;
            padding: 40px 20px;
            display: flex;
            justify-content: center;
        }

        .container {
            width: 100%;
            max-width: 800px;
            background: var(--card-bg);
            padding: 30px 40px;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
        }

        h2 {
            color: var(--primary-color);
            font-weight: 600;
            text-align: center;
            margin-top: 0;
            padding-bottom: 20px;
            border-bottom: 2px solid #eff6ff;
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 500;
            color: #0f172a;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title::before {
            content: '';
            display: block;
            width: 5px;
            height: 24px;
            background-color: var(--primary-color);
            border-radius: 4px;
        }

        .row {
            display: flex;
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .row:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.06);
            border-color: #bfdbfe;
        }

        .left {
            width: 40%;
            font-weight: 500;
            font-size: 1.1rem;
            color: #1e293b;
            display: flex;
            align-items: flex-start;
            padding-top: 5px;
        }

        .right {
            width: 60%;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .list-item {
            background: #ffffff;
            border: 1px dashed #cbd5e1;
            padding: 10px 15px;
            border-radius: 8px;
            color: var(--text-muted);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
        }

        .list-item strong {
            color: var(--primary-color);
            margin-right: 10px;
            min-width: 20px;
        }

        /* รองรับหน้าจอมือถือ */
        @media (max-width: 600px) {
            .row {
                flex-direction: column;
            }
            .left {
                width: 100%;
                margin-bottom: 15px;
                padding-bottom: 15px;
                border-bottom: 1px solid var(--border-color);
            }
            .right {
                width: 100%;
            }
            .container {
                padding: 20px;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <h2>ข้อมูลยานยนต์ (Backup)</h2>

    <div class="section-title">
        รายการยานพาหนะทั้งหมด
    </div>

    <div class="row">
        <div class="left">🛻 รถกระบะ 4 ประตู</div>
        <div class="right">
            <div class="list-item"><strong>1.</strong> ทะเบียน 2ขฒ 8901 กรุงเทพมหานคร</div>
            <div class="list-item"><strong>2.</strong> ทะเบียน ฎณ 4512 กรุงเทพมหานคร</div>
            <div class="list-item"><strong>3.</strong> ทะเบียน กต 7631 เชียงใหม่</div>
        </div>
    </div>

    <div class="row">
        <div class="left">🚛 รถบรรทุก (6 คัน)</div>
        <div class="right">
            <div class="list-item"><strong>1.</strong> ทะเบียน 71-4523 สมุทรปราการ</div>
            <div class="list-item"><strong>2.</strong> ทะเบียน 83-9012 ชลบุรี</div>
            <div class="list-item"><strong>3.</strong> ทะเบียน 70-8819 ปทุมธานี</div>
        </div>
    </div>

    <div class="row">
        <div class="left">🚐 รถตู้ทึบ (6 คัน)</div>
        <div class="right">
            <div class="list-item"><strong>1.</strong> ทะเบียน 1ฒข 3412 กรุงเทพมหานคร</div>
            <div class="list-item"><strong>2.</strong> ทะเบียน 2ฒง 9981 กรุงเทพมหานคร</div>
            <div class="list-item"><strong>3.</strong> ทะเบียน 3ฒช 5520 นนทบุรี</div>
        </div>
    </div>

    <div class="row">
        <div class="left">🚜 รถห้องสรง</div>
        <div class="right">
            <div class="list-item"><strong>1.</strong> ทะเบียน 82-1105 นครปฐม</div>
            <div class="list-item"><strong>2.</strong> ทะเบียน 80-4432 ระยอง</div>
            <div class="list-item"><strong>3.</strong> ทะเบียน 83-7761 สมุทรสาคร</div>
        </div>
    </div>

</div>

</body>
</html>