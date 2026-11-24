<style>
    html {
        height: 100%;
    }
    body {
        font-family: 'Prompt', sans-serif;
        background-color: #f0f2f5;
        display: flex;
        flex-direction: column;
        min-height: 100vh; 
        margin: 0;
    }

    /* ตัวคลุมเนื้อหาหลัก เพื่อให้ยืดเต็มพื้นที่และดัน Footer ลงไปล่างสุด */
    .content-wrapper {
        flex: 1 0 auto;
    }
    .main-footer {
        background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
        color: rgba(255, 255, 255, 0.7);
        padding: 1.5rem 0;
        font-family: 'Prompt', sans-serif;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        width: 100%;
        margin-top: auto; /* หัวใจสำคัญ: ดันตัวเองลงไปล่างสุดของ flex container */
        flex-shrink: 0;
    }
    .footer-text {
        font-size: 0.9rem;
        font-weight: 300;
        letter-spacing: 0.5px;
    }
</style>
<footer class="main-footer">
    <div class="container text-center">
        <div class="footer-text">
            &copy; 2026 กรมโยธาธิการและผังเมือง. All Rights Reserved.
        </div>
    </div>
</footer>
