#include <iostream>
#include <stdio.h>
#include <string>
#include <fstream>
#include <string.h>
using namespace std;
int main(int argc,char*argv[])
{
    char * servname = NULL;
    char * servcmd  = NULL;
    FILE * fp;
    char buffer[5120];
    string php = "/usr/bin/php";

    if(argc == 1){
        //还是执行php 输出信息
        cout << " no cmd and no path " << php << endl;
    }else if(argc == 2){
        servcmd=argv[1];
    }else{
        servname=argv[1];
        servcmd=argv[2];
    };
    //  cout << " serve name is "<<servname << " and cmd is "<<servcmd<<endl;
    string cmd = php + " ../frame/shell/server.php " + (servname==NULL?" ":servname) + "  "+ (servcmd==NULL?" ":servcmd) ;
    // cout << cmd << endl;
    fp = popen(cmd.c_str(), "r");
    while(!feof(fp))
    {
        fgets(buffer,sizeof(buffer),fp);
        printf("%s",buffer);
        memset(buffer,0,sizeof(buffer));
    }
    pclose(fp);
    return 0;
}