using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.IO;
using System.Net;
using System.ServiceProcess;
using System.Threading;
using System.Web.Script.Serialization;

class PindaiService : ServiceBase {
    readonly string root = AppDomain.CurrentDomain.BaseDirectory;
    readonly string data;
    readonly ManualResetEvent stopped = new ManualResetEvent(false);
    readonly object gate = new object();
    Thread worker;
    volatile bool stopping;

    PindaiService(string[] args) {
        ServiceName = "PindaiIn";
        data = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.CommonApplicationData), "PindaiIn", "data");
        // Explicit overrides are only used by the elevated, isolated service smoke test.
        for(int i=0;i+1<args.Length;i+=2) {
            if(args[i]=="--name") ServiceName=args[i+1];
            else if(args[i]=="--data") data=Path.GetFullPath(args[i+1]);
        }
        AutoLog = false; CanStop = true; CanShutdown = true;
    }
    static void Main(string[] args) { ServiceBase.Run(new PindaiService(args)); }
    void Log(string value) {
        try { File.AppendAllText(Path.Combine(data,"service.log"),DateTime.UtcNow.ToString("s")+"Z "+value+Environment.NewLine); } catch { }
    }
    void RunManager(string action) {
        string shell = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.System),"WindowsPowerShell", "v1.0", "powershell.exe");
        var info = new ProcessStartInfo(shell,"-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File \""+Path.Combine(root,"Manage-Pindai.ps1")+"\" -DataRoot \""+data+"\" -Action "+action+" -Headless -NoBrowser");
        info.UseShellExecute=false; info.CreateNoWindow=true;
        info.RedirectStandardOutput=true; info.RedirectStandardError=true;
        using (var process=Process.Start(info)) {
            var output=process.StandardOutput.ReadToEndAsync();
            var error=process.StandardError.ReadToEndAsync();
            if (!process.WaitForExit(180000)) { process.Kill(); throw new Exception("Manager timeout."); }
            if (process.ExitCode != 0) { Log(output.Result+error.Result); throw new Exception("Manager failed: "+action); }
        }
    }
    bool Healthy() {
        try {
            var json=new JavaScriptSerializer();
            var state=json.Deserialize<Dictionary<string,object>>(File.ReadAllText(Path.Combine(data,"config","desktop.json")));
            foreach (string key in new string[]{"mysql_pid","web_pid"}) {
                using(var process=Process.GetProcessById(Convert.ToInt32(state[key]))) { if(process.HasExited) return false; }
            }
            var request=(HttpWebRequest)WebRequest.Create("http://127.0.0.1:"+state["web_port"]+"/_desktop/health");
            request.Proxy=null; request.Timeout=3000;
            using(var response=request.GetResponse())
            using(var reader=new StreamReader(response.GetResponseStream())) {
                var health=json.Deserialize<Dictionary<string,object>>(reader.ReadToEnd());
                return (string)health["instance"]==(string)state["instance"];
            }
        } catch { return false; }
    }
    protected override void OnStart(string[] args) {
        stopping=false; stopped.Reset();
        worker=new Thread(delegate() {
            try {
                lock(gate) { if(!stopping) RunManager("Start"); }
                Log("Service ready.");
                int failures=0;
                while(!stopped.WaitOne(10000)) {
                    if(Healthy()) { failures=0; continue; }
                    if(++failures < 3) continue;
                    lock(gate) { if(stopping) break; Log("Restarting local servers."); RunManager("Stop"); RunManager("Start"); }
                    failures=0;
                }
            } catch(Exception error) { Log(error.Message); if(!stopping) Environment.Exit(1); }
        });
        worker.IsBackground=true; worker.Start();
    }
    protected override void OnStop() {
        stopping=true; stopped.Set(); RequestAdditionalTime(240000);
        lock(gate) { try { RunManager("Stop"); Log("Service stopped."); } catch(Exception error) { Log(error.Message); throw; } }
        if(worker!=null) worker.Join(5000);
    }
    protected override void OnShutdown() {
        stopping=true; stopped.Set();
        lock(gate) { try { RunManager("Stop"); } catch(Exception error) { Log(error.Message); } }
    }
}
