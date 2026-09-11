<style>
:root{
  --navy:#071d49;--blue:#164cc0;--blue2:#285fe0;--red:#ef1f2f;--yellow:#ffc400;
  --green:#12b76a;--ink:#101828;--muted:#667085;--line:#e4e7ec;--bg:#f7f9fc;--white:#fff;
  --shadow:0 22px 65px rgba(7,29,73,.12);--radius:22px
}
*{box-sizing:border-box} html{scroll-behavior:smooth}
body{margin:0;font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:var(--ink);background:#fff;line-height:1.55}
a{text-decoration:none;color:inherit} button,input{font:inherit}
.container{width:min(1140px,calc(100% - 36px));margin:auto}
.top{background:var(--navy);color:#fff;border-bottom:1px solid rgba(255,255,255,.12)}
.topin{min-height:62px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px;padding:8px 0}
.logo{font-weight:900;font-size:18px;letter-spacing:-.03em}.logo span{color:#7fa4ff}
.topnote{font-size:13px;color:#d0d5dd}
.hero{background:linear-gradient(135deg,#f8fbff 0%,#eef4ff 55%,#fff 100%);padding:54px 0 74px;overflow:hidden}
.hero-grid{display:grid;grid-template-columns:1.02fr .98fr;gap:54px;align-items:center}
.eyebrow{display:inline-flex;align-items:center;gap:8px;background:#e8efff;color:var(--blue);border:1px solid #d6e2ff;border-radius:999px;padding:7px 11px;font-size:11px;font-weight:900;letter-spacing:.09em;text-transform:uppercase;margin-bottom:18px}
h1{font-size:clamp(36px,5.3vw,60px);line-height:1.05;letter-spacing:-.05em;margin:0 0 20px;color:var(--navy)}
h1 em{font-style:normal;color:var(--red)}
.hero p{font-size:18px;color:#475467;max-width:650px;margin:0 0 24px}
.points{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin:0 0 28px;padding:0;list-style:none}
.points li{font-size:14px;font-weight:700;color:#344054}.points li:before{content:"✓";color:var(--green);font-weight:900;margin-right:8px}
.primary{display:inline-flex;align-items:center;justify-content:center;gap:9px;border:0;border-radius:13px;padding:16px 24px;background:linear-gradient(135deg,var(--blue2),var(--blue));color:#fff;font-weight:900;box-shadow:0 14px 30px rgba(22,76,192,.25);cursor:pointer;transition:.2s;width:auto}
.primary:hover{transform:translateY(-2px);box-shadow:0 18px 38px rgba(22,76,192,.32)}
.primary.disabled{background:#98a2b3;box-shadow:none;cursor:not-allowed}
.primary.disabled:hover{transform:none}
.micro{font-size:12px;color:#667085;margin-top:11px}.micro b{color:#344054}
.hero-card{position:relative;background:#fff;border:1px solid #dce5f4;border-radius:25px;padding:26px;box-shadow:var(--shadow)}
.hero-card h3{margin:0 0 6px;font-size:18px;color:var(--navy)}
.hero-card p{margin:0 0 18px;color:var(--muted);font-size:14px}
.price-float{background:#fff;border:1px solid #e4e7ec;border-radius:17px;padding:16px 18px;display:inline-flex;flex-direction:column;box-shadow:0 14px 35px rgba(16,24,40,.15)}
.price-float small{display:block;color:var(--muted);font-size:10px;font-weight:800;text-transform:uppercase}.price-float strong{font-size:32px;color:var(--navy)}
.section{padding:84px 0}.soft{background:var(--bg)}
.center{text-align:center}.kicker{font-size:11px;font-weight:900;letter-spacing:.13em;text-transform:uppercase;color:var(--blue);margin-bottom:9px}
h2{font-size:clamp(28px,4vw,42px);line-height:1.12;letter-spacing:-.04em;margin:0 0 13px;color:var(--navy)}
.lead{max-width:700px;margin:auto;color:var(--muted);font-size:17px}
.problem{margin-top:42px;display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
.card{background:#fff;border:1px solid var(--line);border-radius:20px;padding:25px}
.icon{width:46px;height:46px;border-radius:14px;background:#edf3ff;color:var(--blue);display:grid;place-items:center;font-size:21px;margin-bottom:16px}
.card h3{margin:0 0 8px;font-size:19px;color:var(--navy)}.card p{margin:0;color:var(--muted);font-size:14px}
.audit-grid{margin-top:42px;display:grid;grid-template-columns:repeat(2,1fr);gap:15px}
.audit-item{background:#fff;border:1px solid var(--line);border-radius:18px;padding:20px;display:flex;gap:15px}
.audit-item .icon{flex:none;margin:0;width:42px;height:42px}.audit-item h3{margin:0 0 5px;font-size:16px}.audit-item p{margin:0;color:var(--muted);font-size:13px}
.offer{display:grid;grid-template-columns:.9fr 1.1fr;gap:48px;align-items:center}
.offer-copy h2{margin-bottom:18px}.offer-copy p{color:var(--muted)}
.offer-list{padding:0;margin:23px 0;list-style:none}.offer-list li{margin:11px 0;font-size:15px}.offer-list li:before{content:"✓";color:var(--green);font-weight:900;margin-right:9px}
.buybox{background:#fff;border:1px solid #dbe3f0;border-radius:25px;box-shadow:var(--shadow);padding:29px}
.buybox-head{display:flex;justify-content:space-between;align-items:start;gap:20px}.tag{background:#fff4cc;color:#8a5b00;padding:6px 9px;border-radius:999px;font-size:10px;font-weight:900}
.buybox h3{font-size:23px;margin:0;color:var(--navy)}.price{font-size:48px;font-weight:950;letter-spacing:-.05em;color:var(--navy);margin:12px 0 6px}
.buybtn{width:100%;margin-top:17px}.secure{display:flex;justify-content:center;gap:8px;color:#667085;font-size:11px;margin-top:10px}
.buyerror{margin-top:12px;padding:10px 12px;border-radius:10px;background:#fef3f2;border:1px solid #fecdca;color:#b42318;font-size:13px}
.steps{margin-top:42px;display:grid;grid-template-columns:repeat(4,1fr);gap:16px}.step{padding:22px;border:1px solid var(--line);background:#fff;border-radius:18px}.num{font-size:11px;color:var(--blue);font-weight:900;margin-bottom:25px}.step h3{font-size:17px;margin:0 0 7px}.step p{margin:0;color:var(--muted);font-size:13px}
.trust{display:grid;grid-template-columns:1fr 1fr;gap:40px;align-items:center}.statgrid{display:grid;grid-template-columns:1fr 1fr;gap:13px}.stat{background:#fff;border:1px solid var(--line);border-radius:18px;padding:21px}.stat strong{font-size:29px;color:var(--navy);display:block}.stat span{font-size:12px;color:var(--muted)}
.faq{max-width:820px;margin:38px auto 0}.faq details{border-bottom:1px solid var(--line);padding:17px 0}.faq summary{cursor:pointer;font-weight:800;list-style:none;display:flex;justify-content:space-between}.faq summary::-webkit-details-marker{display:none}.faq summary:after{content:"+";color:var(--blue);font-size:20px}.faq details[open] summary:after{content:"−"}.faq p{color:var(--muted);font-size:14px;margin:10px 28px 0 0}
.final{background:var(--navy);color:#fff;padding:75px 0}.final h2{color:#fff;max-width:750px;margin-left:auto;margin-right:auto}.final p{color:#cbd5e1;max-width:620px;margin:0 auto 23px}.footer{background:#06152f;color:#98a2b3;padding:24px 0;font-size:11px;text-align:center}
.sticky{display:none}
.badge-row{display:flex;flex-wrap:wrap;gap:10px;margin:0 0 24px}
.badge{background:#fff;border:1px solid var(--line);border-radius:12px;padding:12px 16px;flex:1;min-width:140px}
.badge small{display:block;color:var(--muted);font-size:10px;font-weight:800;text-transform:uppercase;margin-bottom:3px}
.badge strong{display:block;font-size:15px;color:var(--navy)}
@media(max-width:900px){
 .hero-grid,.offer,.trust{grid-template-columns:1fr}.problem{grid-template-columns:1fr}.steps{grid-template-columns:1fr 1fr}.offer-copy{order:1}.buybox{order:0}
}
@media(max-width:650px){
 .container{width:calc(100% - 28px)}.topnote{font-size:11px}.hero{padding:42px 0 60px}h1{font-size:38px}.hero p{font-size:16px}.points{grid-template-columns:1fr}.section{padding:65px 0}.audit-grid{grid-template-columns:1fr}.steps{grid-template-columns:1fr}.price-float{left:10px;bottom:12px}.sticky{display:flex;position:fixed;z-index:90;left:0;right:0;bottom:0;background:#fff;border-top:1px solid var(--line);padding:9px 11px;gap:10px;box-shadow:0 -8px 25px rgba(16,24,40,.12)}.sticky-price{flex:1;font-size:10px;color:var(--muted);display:flex;flex-direction:column;justify-content:center}.sticky-price strong{font-size:16px;color:var(--navy)}.sticky .primary{padding:12px 14px;font-size:13px}.final{padding-bottom:105px}
}
</style>
